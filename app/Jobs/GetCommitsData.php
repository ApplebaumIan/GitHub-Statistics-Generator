<?php

namespace App\Jobs;

use App\Http\Controllers\Commits;
use App\Http\Controllers\GitHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $data = Commits::get($this->owner, $this->repo);
        $mermaid = (new GitHub)->mermaid($data, $this->repo, 'Commits');
        cache()->put("chart_commits_{$this->owner}-{$this->repo}", $mermaid, now()->addHours(1));

    }
}
