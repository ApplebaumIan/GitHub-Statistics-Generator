<?php

namespace App\Jobs;

use App\Http\Controllers\Commits;
use App\Http\Controllers\PullRequests;
use App\Http\Controllers\Reviews;
use App\Http\Controllers\GitHub;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateChartImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $type;
    public string $owner;
    public string $repo;

    public function __construct(string $type, string $owner, string $repo)
    {
        $this->type  = $type;
        $this->owner = $owner;
        $this->repo  = $repo;
    }

    public function handle(): void
    {
        // 1) Fetch the raw chart data array
        $chartData = match ($this->type) {
            'pull-requests' => PullRequests::get($this->owner, $this->repo),
            'commits'       => Commits::get($this->owner, $this->repo),
            'reviews'       => Reviews::get($this->owner, $this->repo),
            default         => null,
        };

        if (! is_array($chartData)) {
            // Unknown type or fetch failed—nothing to do
            return;
        }

        // 2) Render to mermaid text
        $mermaid = (new GitHub)->mermaid(
            $chartData,
            $this->owner,
            $this->repo
        );

        // 3) Cache the mermaid text (so your HTTP endpoint can serve it)
        $cacheKey = "chart_{$this->type}_{$this->owner}-{$this->repo}";
        Cache::put($cacheKey, $mermaid, now()->addMinutes(Commits::CHART_CACHE_TTL));

        // 4) (Optional) generate & persist your image URL if you like
        // $url = (new GitHub)->mermaidUrl($mermaid, '#33a3ff');
        // Cache::put("url_{$cacheKey}", $url, now()->addMinutes(Commits::CHART_CACHE_TTL));
    }
}
