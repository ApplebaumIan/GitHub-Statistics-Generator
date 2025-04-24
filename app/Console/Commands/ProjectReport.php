<?php

namespace App\Console\Commands;

use App\Http\Controllers\Commits;
use App\Http\Controllers\GitHub;
use App\Http\Controllers\PullRequests;
use App\Http\Controllers\Reviews;
use App\Jobs\GetCommitsData;
use App\Jobs\GetPullRequestData;
use App\Jobs\GetReviewsData;
use App\Models\ChartRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProjectReport extends Command
{
    protected $signature = 'project:report {repo?}';

    protected $description = 'Generate a Markdown report of a GitHub project with Mermaid charts';

    public function handle(): int
    {
        $repoPath = $this->argument('repo');
        if ($repoPath === null) {
            $repoPath = $this->choice(
                'Choose a repository',
                ChartRequest::all()
                    ->sortBy('hit_count')
                    ->pluck('cache_key')
                    ->map(function ($key) {
                        // Remove prefix (e.g., "pull-requests_", "reviews_", "commits_")
                        $key = preg_replace('/^(pull-requests|commits|reviews)_/', '', $key);
                        // Replace underscores with slashes
                        return str_replace('_', '/', $key);
                    })
                    ->unique() // optional: remove duplicates across metric types
                    ->values()
                    ->toArray()
            );
        }
        [$owner, $repo] = explode('/', $repoPath);

        $gitHub = new GitHub;

//        $pullRequestsData = PullRequests::get($owner, $repo);
//        $reviewsData = Reviews::get($owner, $repo);
//        $commitsData = Commits::get($owner, $repo);

        $repoKey = str_replace('-', '_', $repo); // safer for cache keys

        $cacheKeys = [
            'pull_requests' => "chart_pull-requests_{$owner}-{$repo}",
            'reviews' => "chart_reviews_{$owner}-{$repo}",
            'commits' => "chart_commits_{$owner}-{$repo}",
        ];

// Dispatch
        $pullRequestsLockKey = "lock_chart_pull-requests_{$owner}_{$repo}";
        if(Cache::lock($pullRequestsLockKey, 60)->get()) {
            GetPullRequestData::dispatch($owner, $repo);
        }

        GetReviewsData::dispatch($owner, $repo);

        $commitsLockKey = "lock_chart_commits_{$owner}_{$repo}";
        // Only dispatch if we're allowed to acquire the lock (non-blocking)
        if (Cache::lock($commitsLockKey, 60)->get()) {
            GetCommitsData::dispatch($owner, $repo);
        }
// Show progress bar
        $this->info("Waiting for jobs to complete...");
        $bar = $this->output->createProgressBar(count($cacheKeys));
        $bar->start();

        $results = [];

        while (count($results) < count($cacheKeys)) {
            foreach ($cacheKeys as $label => $key) {
                if (!array_key_exists($label, $results) && Cache::has($key)) {
                    $results[$label] = Cache::get($key);
                    $bar->advance();
                }
            }
            usleep(500000); // wait 0.5s before polling again
        }

        $bar->finish();
        $this->newLine(2);

        $pullRequestsMermaid = $results['pull_requests'];
        $reviewsMermaid = $results['reviews'];
        $commitsMermaid = $results['commits'];


        $markdown = <<<MD
# {$repoPath}

## Pull Requests
```mermaid
---
config:
    themeVariables:
        xyChart:
            plotColorPalette: "#33a3ff"
---
{$pullRequestsMermaid}
```

## Reviews
```mermaid
---
config:
    themeVariables:
        xyChart:
            plotColorPalette: "#70ff33"
---
{$reviewsMermaid}
```

## Commits
```mermaid
---
config:
    themeVariables:
        xyChart:
            plotColorPalette: "#ff9633"
---
{$commitsMermaid}
```
MD;
        // Output to console
        $this->line($markdown);

        // Optional: Save to file
        file_put_contents(storage_path("app/{$repo}-report.md"), $markdown);

        return Command::SUCCESS;
    }
}
