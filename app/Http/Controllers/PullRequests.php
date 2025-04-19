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
        $labels = $chartData['labels'];
        $values = $chartData['datasets'][1]['data'];
        $labelString = implode(', ', array_map(fn ($l) => '"'.$l.'"', $labels));
        $valueString = implode(', ', $values);
        $maxY = max($values);
        $date = Carbon::now()->setTimezone('EST');
        $date = $date->setTimezone('EST')->toDateTimeString();
        $mermaid = <<<EOT
    xychart-beta
    title "Commits — Example"
    x-axis [{$labelString}]
    y-axis "Commits" 0 --> {$maxY}
    bar [{$valueString}]
    EOT;

        $json = $this->serializeMermaidState($mermaid);
        $encoded = $this->encodeMermaid($json);
        //        $encoded = urlencode($encoded);
        $url = "https://mermaid.ink/img/{$encoded}";

        return redirect()->to($url, 301);
    }
}
