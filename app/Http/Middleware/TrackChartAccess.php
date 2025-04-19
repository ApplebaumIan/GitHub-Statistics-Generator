<?php

namespace App\Http\Middleware;

use App\Models\ChartRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackChartAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track for API image routes
        if ($request->is('api/*')) {
            $key = str_replace('/', '_', preg_replace('/^api\//', '', $request->path()));
            ChartRequest::updateOrCreate(
                ['cache_key' => $key],
                ['last_accessed_at' => now()]
            )->increment('hit_count');

        }

        return $response;
    }
}
