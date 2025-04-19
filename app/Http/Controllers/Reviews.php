<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Reviews extends GitHub
{
    public function index(Request $request, $owner, $repo)
    {
        $cacheKey = "reviews_$owner-$repo";

        if ($response = $this->respondWithCachedChart($cacheKey)) {
            return $response;
        }

        $data = $this->get($owner, $repo);
        $mermaid = $this->mermaid($data, $repo, 'Reviews');
        $url = $this->mermaidUrl($mermaid, '#70ff33');

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
        $page = 1;
        $result = [];
        $promises = [];
        $response = Http::get("https://api.github.com/repos/$owner/$repo/pulls?state=all&page=$page&per_page=100");
        $headerLinks = $response->header('Link');
        $totalPages = GitHub::getTotalPagesFromHeaderLinks($headerLinks);
        do {
            $promises[] = Http::withToken(env('GITHUB'))->async()->get("https://api.github.com/repos/$owner/$repo/pulls?state=all&page=$page&per_page=100");
            $page++;
        } while ($page <= $totalPages);

        $responses = Utils::unwrap($promises);
        $promises = [];

        foreach ($responses as $response) {
            if ($response->status() === 401) {
                // Handle 401 Unauthorized error
                // ...
                throw new GitHubTokenUnauthorized;
            }
            $pullRequests = $response->json();

            foreach ($pullRequests as $pullRequest) {
                $promises[] = Http::withToken(env('GITHUB'))->async()->get("https://api.github.com/repos/$owner/$repo/pulls/".$pullRequest['number'].'/reviews');

            }

            $responses = Utils::unwrap($promises);

            foreach ($responses as $reviews_response) {
                $reviews = $reviews_response->json();

                //            $state = $pullRequest['state'];
                foreach ($reviews as $review) {
                    $user = $review['user']['login'];

                    if ($review['user']['login'] == $user) {
                        if (isset($result[$user])) {
                            $result[$user]++;
                        } else {
                            $result[$user] = 1;
                        }
                    }
                }
            }

        }

        /*
  * Chart data
  */

        $data = $result;
        $new = [];
        foreach ($data as $user => $value) {
            $r = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/$user");
            $user = $r['name'] ? $r['name'] : $user;
            $new[$user] = $value;
        }
        arsort($new);
        $data = $new;

        return $new;
    }
}
