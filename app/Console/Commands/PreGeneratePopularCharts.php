<?php

namespace App\Console\Commands;

use App\Jobs\GenerateChartImage;
use App\Models\ChartRequest;
use Illuminate\Console\Command;

class PreGeneratePopularCharts extends Command
{
    protected $signature = 'charts:pregenerate';

    protected $description = 'Pre-generate chart images for frequently accessed keys';

    public function handle(): int
    {
        $this->info('Starting chart pre-generation...');

        ChartRequest::where('last_accessed_at', '>=', now()->subMonths(12))
            ->orderByDesc('hit_count')
            ->take(100)
            ->get()
            ->each(function ($request) {
                $parts = explode('_', $request->cache_key);

                if (count($parts) < 3) {
                    $this->warn("Invalid cache key: {$request->cache_key}");

                    return;
                }

                [$type, $owner, $repo] = [
                    $parts[0],
                    $parts[1],
                    implode('_', array_slice($parts, 2)), // for orgs with underscores
                ];

                $this->info("Dispatching job for: $type / $owner / $repo");

                GenerateChartImage::dispatch($type, $owner, $repo);

                if (ChartRequest::where('last_accessed_at', '<', now()->subYear())->delete()) {
                    $this->info('Old chart requests pruned.');
                }

            });

        return self::SUCCESS;
    }
}
