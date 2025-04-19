<?php

namespace App\Jobs;

use App\Http\Controllers\Commits;
use App\Http\Controllers\PullRequests;
use App\Http\Controllers\Reviews;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateChartImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $type;

    public string $owner;

    public string $repo;

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, string $owner, string $repo)
    {
        $this->type = $type;
        $this->owner = $owner;
        $this->repo = $repo;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        match ($this->type) {
            'pull-requests' => (new PullRequests)->index(request(), $this->owner, $this->repo),
            'commits' => (new Commits)->index(request(), $this->owner, $this->repo),
            'reviews' => (new Reviews)->index(request(), $this->owner, $this->repo),
            default => null,
        };
    }
}
