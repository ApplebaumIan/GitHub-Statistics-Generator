<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Http\Controllers\GitHub;
use App\Http\Controllers\PullRequests;

class GetPullRequestData implements ShouldQueue
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
        $data = PullRequests::get($this->owner, $this->repo);
        $mermaid = (new GitHub)->mermaid($data, $this->repo, 'Pull Requests');
        cache()->put("chart_pull-requests_{$this->owner}-{$this->repo}", $mermaid, now()->addHours(1));
    }
}
