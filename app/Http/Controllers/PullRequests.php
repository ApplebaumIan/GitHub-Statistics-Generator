<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Encoders\PngEncoder;

class PullRequests extends GitHub
{
    public function index(Request $request, $owner, $repo)
    {
        $cacheKey = "pull_requests_$owner-$repo";
        $cachedData = Cache::get($cacheKey);

        if ($response = $this->respondWithCachedChart($cacheKey)) {
            return $response;
        }


        $page = 1;

        $result = [];
        do {
            //            echo $page;
            $response = Http::withToken(env('GITHUB'))->get("https://api.github.com/repos/$owner/$repo/pulls?state=all&page=$page");

            if ($response->status() === 401) {
                // Handle 401 Unauthorized error
                // ...
                throw new GitHubTokenUnauthorized;
            }

            $pullRequests = $response->json();

            foreach ($pullRequests as $pullRequest) {
                $user = $pullRequest['user']['login'];
                $state = $pullRequest['state'];

                if (! isset($result[$user])) {
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
            $page++;
        } while (! empty($pullRequests));

        //        return $result;

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

        /*
        * Chart data
        */

        [$new, $chart] = $this->makeChart($chartData['datasets'][1]['data'], [47, 133, 217], $repo.' number of Pull Requests ', 1);

        /*
         * Output image to browser
         */
        $title = 'pull_requests_';
        $this->saveChart($chart, $title, $owner, $repo, $cacheKey);
        $encoded = $chart->encode(new PngEncoder());

        return response($encoded)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'inline; filename="pull_requests.png"');

        //        return $chartData;
    }
}
