<?php

namespace App\Jobs;

use App\Http\Controllers\GitHub;
use App\Http\Controllers\Reviews;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $data = Reviews::get($this->owner, $this->repo);
        $mermaid = (new GitHub)->mermaid($data, $this->repo, 'Reviews');
        cache()->put("chart_reviews_{$this->owner}-{$this->repo}", $mermaid, now()->addHours(1));
    }
}
