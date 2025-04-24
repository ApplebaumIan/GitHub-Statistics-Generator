<?php

namespace App\Jobs;

use App\Http\Controllers\GitHub;
use App\Http\Controllers\Reviews;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class GetReviewsData implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $owner, public string $repo) {}


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lockKey = "lock_chart_reviews_{$this->owner}_{$this->repo}";
        $owner = $this->owner;
        $repo = $this->repo;
        Cache::lock($lockKey, 60)->block(10, function () use ($owner, $repo) {
            $data = Reviews::get($this->owner, $this->repo);
            $mermaid = (new GitHub)->mermaid($data, $this->repo, 'Reviews');
            cache()->put("chart_reviews_{$this->owner}-{$this->repo}", $mermaid, now()->addHours(1));
        });

    }
}
