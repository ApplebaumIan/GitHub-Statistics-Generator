<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use App\Jobs\GetReviewsData;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Reviews extends GitHub
{
    // Cache TTLs in minutes
    const REVIEWS_CACHE_TTL = 60; // 1 hour
    const PR_CACHE_TTL = 120; // 2 hours
    const USER_CACHE_TTL = 1440; // 24 hours
    const CHART_CACHE_TTL = 60; // 1 hour
    public function mermaid_text(Request $request ,$owner, $repo){
        $chartCacheKey = "chart_reviews_{$owner}-{$repo}";
        $forceRefresh = $request->query('force', false);

        // Dispatch job if not cached or force requested
        if (!Cache::has($chartCacheKey) || $forceRefresh) {
            $lockKey = "lock_chart_reviews_{$owner}_{$repo}";
            $lock = Cache::lock($lockKey, 60);
            if ($lock->get()) {
                try {
                GetReviewsData::dispatch($owner, $repo);
                } finally {
                    $lock->release();
                }
            }
        }

        // If cached, redirect to the chart immediately
        if (Cache::has($chartCacheKey)) {
            $mermaid = Cache::get($chartCacheKey);
            $text = <<<MD
            ---
            config:
                themeVariables:
                    xyChart:
                        plotColorPalette: "#70ff33"
            ---
            {$mermaid}
            MD;

            return response($text,200)->header('Content-Type', 'text/plain');
        }
        return response('processing comeback later',202);
    }
    public function image(Request $request, $owner, $repo)
    {
        $chartCacheKey = "chart_reviews_{$owner}-{$repo}";
        $forceRefresh = $request->query('force', false);
        // Dispatch job if not cached or force requested
        if (!Cache::has($chartCacheKey) || $forceRefresh) {
            $lockKey = "lock_chart_reviews_{$owner}_{$repo}";
            if (Cache::lock($lockKey, 60)->get()) {
                GetReviewsData::dispatch($owner, $repo);
            }
        }

        // If cached, redirect to the chart immediately
        if (Cache::has($chartCacheKey)) {
            $mermaid = Cache::get($chartCacheKey);
            $url = (new GitHub)->mermaidUrl($mermaid, '#33a3ff');

            return redirect()->to($url, 301);
        }

        // Placeholder response while job is processing
        return response('Processing... Try again soon.', 202);
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
        $reviewsCacheKey = "processed_reviews_{$owner}-{$repo}";

        // Try to get cached reviews data
        $cachedData = Cache::get($reviewsCacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // Fetch all required data
        $pulls = self::fetchPullRequests($owner, $repo);
        $reviewData = self::fetchReviewsForPulls($owner, $repo, $pulls);
        $reviewsWithUserNames = self::enrichWithUserData($reviewData);

        // Cache the processed data
        Cache::put($reviewsCacheKey, $reviewsWithUserNames, now()->addMinutes(self::REVIEWS_CACHE_TTL));

        return $reviewsWithUserNames;
    }

    /**
     * Fetch pull requests with caching
     */
    private static function fetchPullRequests($owner, $repo): array
    {
        $pullsCacheKey = "pr_list_{$owner}-{$repo}";
        $etagKey = "etag_pr_list_{$owner}-{$repo}";

        // Try to get cached pull requests
        $cachedPulls = Cache::get($pullsCacheKey);
        if ($cachedPulls) {
            return $cachedPulls;
        }

        $page = 1;
        $allPulls = [];
        $etag = Cache::get($etagKey);

        do {
            // Set conditional header if we have an ETag for the first page
            $headers = [];
            if ($etag && $page === 1) {
                $headers['If-None-Match'] = $etag;
            }

            $response = Http::withToken(env('GITHUB'))
                ->withHeaders($headers)
                ->get("https://api.github.com/repos/$owner/$repo/pulls", [
                    'state' => 'all',
                    'page' => $page,
                    'per_page' => 100
                ]);

            // Handle 304 Not Modified on first page
            if ($page === 1 && $response->status() === 304) {
                return Cache::get($pullsCacheKey) ?? [];
            }

            // Store new ETag from first page
            if ($page === 1) {
                $newEtag = $response->header('ETag');
                if ($newEtag) {
                    Cache::put($etagKey, $newEtag, now()->addDays(30));
                }
            }

            if ($response->status() === 401) {
                throw new GitHubTokenUnauthorized;
            }

            $pullRequests = $response->json();
            if (empty($pullRequests)) {
                break;
            }

            // Extract only the data we need
            foreach ($pullRequests as $pull) {
                $allPulls[] = [
                    'number' => $pull['number'],
                    'updated_at' => $pull['updated_at']
                ];
            }

            // Check if there are more pages
            $linkHeader = $response->header('Link');
            $hasNextPage = $linkHeader && strpos($linkHeader, 'rel="next"') !== false;

            if (!$hasNextPage) {
                break;
            }

            $page++;
        } while (true);

        // Cache the pull requests
        Cache::put($pullsCacheKey, $allPulls, now()->addMinutes(self::PR_CACHE_TTL));

        return $allPulls;
    }

    /**
     * Fetch reviews for multiple pull requests with batch processing
     */
    private static function fetchReviewsForPulls($owner, $repo, array $pulls): array
    {
        $result = [];
        $promises = [];
        $batchSize = 25; // Process in smaller batches to avoid overwhelming the API

        $pullBatches = array_chunk($pulls, $batchSize);

        foreach ($pullBatches as $pullBatch) {
            $batchPromises = [];

            foreach ($pullBatch as $pull) {
                $pullNumber = $pull['number'];
                $reviewsCacheKey = "reviews_pull_{$owner}-{$repo}-{$pullNumber}";

                // Check cache for this specific PR's reviews
                $cachedReviews = Cache::get($reviewsCacheKey);

                if ($cachedReviews) {
                    // Use cached data
                    self::processReviews($cachedReviews, $result);
                } else {
                    // Need to fetch from API
                    $batchPromises[$pullNumber] = Http::withToken(env('GITHUB'))
                        ->async()
                        ->get("https://api.github.com/repos/$owner/$repo/pulls/{$pullNumber}/reviews");
                }
            }

            // Process any API requests needed for this batch
            if (!empty($batchPromises)) {
                $responses = Utils::unwrap($batchPromises);

                foreach ($responses as $pullNumber => $response) {
                    if ($response->successful()) {
                        $reviews = $response->json();

                        // Cache these reviews
                        $reviewsCacheKey = "reviews_pull_{$owner}-{$repo}-{$pullNumber}";
                        Cache::put($reviewsCacheKey, $reviews, now()->addMinutes(self::PR_CACHE_TTL));

                        // Process the reviews
                        self::processReviews($reviews, $result);
                    }
                }
            }

            // Small delay to avoid rate limiting
            if (count($pullBatches) > 1) {
                usleep(100000); // 100ms
            }
        }

        return $result;
    }

    /**
     * Process reviews and count them by user
     */
    private static function processReviews(array $reviews, array &$result): void
    {
        foreach ($reviews as $review) {
            if (!isset($review['user']) || !isset($review['user']['login'])) {
                continue;
            }

            $user = $review['user']['login'];

            if (isset($result[$user])) {
                $result[$user]++;
            } else {
                $result[$user] = 1;
            }
        }
    }

    /**
     * Enrich review data with user names
     */
    private static function enrichWithUserData(array $reviewData): array
    {
        $enriched = [];

        foreach ($reviewData as $login => $count) {
            $userCacheKey = "github_user_" . md5($login);

            // Try to get cached user data
            $userData = Cache::get($userCacheKey);

            if (!$userData) {
                // Fetch from API if not cached
                $response = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/" . urlencode($login));

                if ($response->successful()) {
                    $userData = $response->json();
                    // Cache the user data
                    Cache::put($userCacheKey, $userData, now()->addMinutes(self::USER_CACHE_TTL));
                } else {
                    // If API request fails, use original login
                    $userData = ['name' => $login];
                }
            }

            $displayName = $userData['name'] ?? $login;
            $enriched[$displayName] = $count;
        }

        arsort($enriched);
        return $enriched;
    }
}
