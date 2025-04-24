<?php

namespace App\Jobs;

use App\Http\Controllers\Commits;
use App\Http\Controllers\GitHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class GetCommitsData implements ShouldQueue
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
        $lockKey = "lock_chart_commits_{$this->owner}_{$this->repo}";
        $owner = $this->owner;
        $repo = $this->repo;
        Cache::lock($lockKey, 60)->block(10, function () use ($owner, $repo) {
            // Only one process can be here at a time
            $data = Commits::get($this->owner, $this->repo);
            $mermaid = (new GitHub)->mermaid($data, $this->repo, 'Commits');
            cache()->put("chart_commits_{$this->owner}-{$this->repo}", $mermaid, now()->addHours(1));
        });
    }
}
