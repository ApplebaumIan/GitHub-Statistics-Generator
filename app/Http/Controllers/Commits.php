<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Commits extends GitHub
{
    // Cache TTLs in minutes
    const COMMITS_CACHE_TTL = 60; // 1 hour
    const USER_CACHE_TTL = 1440;  // 24 hours
    const CHART_CACHE_TTL = 60;   // 1 hour

    public function index(Request $request, $owner, $repo)
    {
        $chartCacheKey = "chart_commits_{$owner}-{$repo}";

        // Try to return cached chart first
        if ($response = $this->respondWithCachedChart($chartCacheKey)) {
            return $response;
        }

        // Get commit data (cached or fresh)
        $data = $this->get($owner, $repo);

        // Generate chart
        $mermaid = $this->mermaid($data, $repo, 'Commits');
        $url = $this->mermaidUrl($mermaid, '#ff9633');

        // Cache the chart URL
        Cache::put($chartCacheKey, $url, now()->addMinutes(self::CHART_CACHE_TTL));

        return redirect()->to($url, 301);
    }

    /**
     * @return array[]
     *
     * @throws GitHubTokenUnauthorized
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Throwable
     */
    public static function get($owner, $repo): array
    {
        $commitsCacheKey = "raw_commits_{$owner}-{$repo}";

        // Try to get cached commit counts
        $commit_counts = Cache::get($commitsCacheKey);

        if (!$commit_counts) {
            $commit_counts = self::fetchCommits($owner, $repo);

            // Cache the commit counts
            Cache::put($commitsCacheKey, $commit_counts, now()->addMinutes(self::COMMITS_CACHE_TTL));
        }

        // Get user details (with caching)
        $data = self::getUserDetails($commit_counts);

        return $data;
    }

    /**
     * Fetch commits from GitHub API with proper caching
     */
    private static function fetchCommits($owner, $repo): array
    {
        $commit_counts = [];
        $page = 1;

        // Check if we have ETag for this repo
        $etagKey = "etag_commits_{$owner}-{$repo}";
        $etag = Cache::get($etagKey);

        // Make initial request with conditional header if we have an ETag
        $headers = [];
        if ($etag) {
            $headers['If-None-Match'] = $etag;
        }

        $response = Http::withToken(env('GITHUB'))
            ->withHeaders($headers)
            ->get("https://api.github.com/repos/$owner/$repo/commits", [
                'page' => $page,
                'per_page' => 100
            ]);

        // If we got a 304 Not Modified, return the cached data
        if ($response->status() === 304) {
            return Cache::get("raw_commits_{$owner}-{$repo}") ?? [];
        }

        // Store the new ETag for future requests
        $newEtag = $response->header('ETag');
        if ($newEtag) {
            Cache::put($etagKey, $newEtag, now()->addDays(30));
        }

        // Get total pages from header
        $headerLinks = $response->header('Link');
        $totalPages = GitHub::getTotalPagesFromHeaderLinks($headerLinks);

        // Process first page results
        self::processCommitResponse($response, $commit_counts);

        // Fetch remaining pages if needed
        if ($totalPages > 1) {
            $promises = [];

            for ($page = 2; $page <= $totalPages; $page++) {
                $promises[] = Http::withToken(env('GITHUB'))
                    ->async()
                    ->get("https://api.github.com/repos/$owner/$repo/commits", [
                        'page' => $page,
                        'per_page' => 100
                    ]);
            }

            $responses = Utils::unwrap($promises);

            foreach ($responses as $response) {
                self::processCommitResponse($response, $commit_counts);
            }
        }

        return $commit_counts;
    }

    /**
     * Process a single commit response
     */
    private static function processCommitResponse($response, &$commit_counts): void
    {
        if ($response->status() === 401) {
            throw new GitHubTokenUnauthorized;
        }

        $commits = $response->json();

        foreach ($commits as $commit) {
            $author_name = $commit['commit']['author']['name'];
            if (isset($commit_counts[$author_name])) {
                $commit_counts[$author_name]++;
            } else {
                $commit_counts[$author_name] = 1;
            }
        }
    }

    /**
     * Get user details with caching
     */
    private static function getUserDetails(array $commit_counts): array
    {
        $new = [];

        foreach ($commit_counts as $user => $value) {
            // Cache key for this specific user
            $userCacheKey = "github_user_" . md5($user);

            // Try to get cached user data
            $userData = Cache::get($userCacheKey);

            if (!$userData) {
                // Fetch from API if not cached
                $response = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/" . urlencode($user));

                if ($response->successful()) {
                    $userData = $response->json();
                    // Cache the user data
                    Cache::put($userCacheKey, $userData, now()->addMinutes(self::USER_CACHE_TTL));
                } else {
                    // If API request fails, use original name
                    $userData = ['name' => $user];
                }
            }

            $displayName = $userData['name'] ?? $user;
            $new[$displayName] = $value;
        }

        arsort($new);
        return $new;
    }
}
