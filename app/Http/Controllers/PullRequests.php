<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PullRequests extends GitHub
{
    // Cache TTLs in minutes
    const PR_CACHE_TTL = 60; // 1 hour
    const CHART_CACHE_TTL = 60; // 1 hour

    public function index(Request $request, $owner, $repo)
    {
        $chartCacheKey = "chart_pull-requests_{$owner}-{$repo}";

        // Try to return cached chart first
        if ($response = $this->respondWithCachedChart($chartCacheKey)) {
            return $response;
        }

        // Get PR data (cached or fresh)
        $data = $this->get($owner, $repo);

        // Generate chart
        $mermaid = $this->mermaid($data, $repo, 'Pull Requests');
        $url = $this->mermaidUrl($mermaid, '#33a3ff');

        // Cache the chart URL
        Cache::put($chartCacheKey, $url, now()->addMinutes(self::CHART_CACHE_TTL));

        return redirect()->to($url, 301);
    }

    /**
     * @throws GitHubTokenUnauthorized
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public static function get($owner, $repo): array
    {
        $prCacheKey = "raw_pull-requests_{$owner}-{$repo}";

        // Try to get cached PR data
        $cachedData = Cache::get($prCacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // Fetch and process PR data if not cached
        $result = self::fetchPullRequests($owner, $repo);

        // Transform data for chart
        $chartData = [
            'labels' => array_keys($result),
            'datasets' => [
                [
                    'label' => 'Opened',
                    'backgroundColor' => '#f87979',
                    'data' => array_map(function ($item) {
                        return $item['opened'];
                    }, $result),
                ],
                [
                    'label' => 'Closed',
                    'backgroundColor' => '#00c853',
                    'data' => array_map(function ($item) {
                        return $item['closed'];
                    }, $result),
                ],
            ],
        ];

        $data = $chartData['datasets'][1]['data'];
        arsort($data);

        // Cache the processed data
        Cache::put($prCacheKey, $data, now()->addMinutes(self::PR_CACHE_TTL));

        return $data;
    }

    /**
     * Fetch pull requests with pagination and ETag support
     */
    private static function fetchPullRequests($owner, $repo): array
    {
        $result = [];
        $page = 1;
        $etagKey = "etag_pull-requests_{$owner}-{$repo}";
        $etag = Cache::get($etagKey);

        do {
            // Set conditional header if we have an ETag
            $headers = [];
            if ($etag && $page === 1) {
                $headers['If-None-Match'] = $etag;
            }

            $response = Http::withToken(env('GITHUB'))
                ->withHeaders($headers)
                ->get("https://api.github.com/repos/$owner/$repo/pulls", [
                    'state' => 'all',
                    'page' => $page,
                    'per_page' => 100 // Maximize per_page to reduce number of requests
                ]);

            // Handle 304 Not Modified on first page
            if ($page === 1 && $response->status() === 304) {
                $prCacheKey = "raw_pull-requests_{$owner}-{$repo}";
                return Cache::get($prCacheKey) ?? [];
            }

            // Store new ETag from first page
            if ($page === 1) {
                $newEtag = $response->header('ETag');
                if ($newEtag) {
                    Cache::put($etagKey, $newEtag, now()->addDays(30));
                }
            }

            // Handle authorization errors
            if ($response->status() === 401) {
                throw new GitHubTokenUnauthorized;
            }

            $pullRequests = $response->json();

            // Process pull requests data
            foreach ($pullRequests as $pullRequest) {
                $user = $pullRequest['user']['login'];
                $state = $pullRequest['state'];

                if (!isset($result[$user])) {
                    $result[$user] = [
                        'opened' => 0,
                        'closed' => 0,
                    ];
                }

                if ($state === 'open') {
                    $result[$user]['opened']++;
                } elseif ($state === 'closed') {
                    $result[$user]['closed']++;
                }
            }

            // Check pagination header for rate limit optimization
            $linkHeader = $response->header('Link');
            $hasNextPage = $linkHeader && strpos($linkHeader, 'rel="next"') !== false;

            // Only increment page if there are more pages to fetch
            if ($hasNextPage) {
                $page++;
            } else {
                break; // Exit loop if no more pages
            }

        } while (true); // We'll break from inside the loop based on pagination

        return $result;
    }
}
