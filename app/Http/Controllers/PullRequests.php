<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PullRequests extends GitHub
{
    public function index(Request $request, $owner, $repo)
    {
        $cacheKey = "pull-requests_$owner-$repo";
        //        $cachedData = Cache::get($cacheKey);

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
        $data =
          $chartData['datasets'][1]['data'];
        arsort($data);

//        $data = $sort;
        $mermaid = $this->getMermaid($data, $repo, "Pull Requests");
        $url = $this->mermaidUrl($mermaid, '#33a3ff');
//        dd($url);
        return redirect()->to($url, 301);
    }

    /**
     * @param array $chartData
     * @param $repo
     * @return string
     */

}
