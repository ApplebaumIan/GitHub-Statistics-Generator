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
        if (app()->runningInConsole()) {
            return $next($request);
        }

        $response = $next($request);

        // Only track for API image routes
        if ($request->is('api/*')) {
            $key = preg_replace('/^api\//', '', $request->path());
            $key = preg_replace('#/mermaid$#', '', $key);
            $key = str_replace('/', '_', $key);

            try {
                ChartRequest::updateOrCreate(
                    ['cache_key' => $key],
                    ['last_accessed_at' => now()]
                )->increment('hit_count');
            } catch (\Exception $e) {
                logger()->error($e);
            }
        }

        return $response;
    }

}
