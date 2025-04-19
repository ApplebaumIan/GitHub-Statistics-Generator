<?php

namespace App\Http\Controllers;

use App\Exceptions\GitHubTokenUnauthorized;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\PngEncoder;

class Commits extends GitHub
{
    public function index(Request $request, $owner, $repo)
    {
        $cacheKey = "commits_$owner-$repo";
        $cachedData = Cache::get($cacheKey);

        if ($response = $this->respondWithCachedChart($cacheKey)) {
            return $response;
        }

        $promises = [];
        $page = 1;
        $response = Http::get("https://api.github.com/repos/$owner/$repo/commits?page=$page&per_page=100");
        $headerLinks = $response->header('Link');
        $totalPages = $this->getTotalPagesFromHeaderLinks($headerLinks);
        do {
            $promises[] = Http::withToken(env('GITHUB'))->async()->get("https://api.github.com/repos/$owner/$repo/commits?page=$page&per_page=100");
            $page++;
        } while ($page <= $totalPages);

        $responses = Utils::unwrap($promises);

        $commit_counts = [];
        foreach ($responses as $response) {
            if ($response->status() === 401) {
                // Handle 401 Unauthorized error
                // ...
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

        /*
  * Chart data
  */

        $data = $commit_counts;
        $new = [];
        foreach ($data as $user => $value) {
            $r = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/$user");
            if (! empty($r)) {
                $user = isset($r['name']) ? $r['name'] : $user;
                $new[$user] = $value;
            }
        }
        arsort($new);
        $data = $new;
        $mermaid = $this->getMermaid($data, $repo, "Commits");
        $url = $this->mermaidUrl($mermaid, '#ff9633');
        return redirect()->to($url, 301);
    }
}
