<?php

namespace App\Console\Commands;

use App\Http\Controllers\Commits;
use App\Http\Controllers\GitHub;
use App\Http\Controllers\PullRequests;
use App\Http\Controllers\Reviews;
use Illuminate\Console\Command;

class ProjectReport extends Command
{
    protected $signature = 'project:report {repo}';

    protected $description = 'Generate a Markdown report of a GitHub project with Mermaid charts';

    public function handle(): int
    {
        $repoPath = $this->argument('repo');
        [$owner, $repo] = explode('/', $repoPath);

        $gitHub = new GitHub;

        $pullRequestsData = PullRequests::get($owner, $repo);
        $reviewsData = Reviews::get($owner, $repo);
        $commitsData = Commits::get($owner, $repo);

        $pullRequestsMermaid = $gitHub->mermaid($pullRequestsData, $repo, 'Pull Requests');
        $reviewsMermaid = $gitHub->mermaid($reviewsData, $repo, 'Reviews');
        $commitsMermaid = $gitHub->mermaid($commitsData, $repo, 'Commits');

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
