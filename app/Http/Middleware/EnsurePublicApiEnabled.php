<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gates the whole public/v2 group on at least one scope being live. */
class EnsurePublicApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('restarters.features.public_events_api', false)
            && !config('restarters.features.public_repairs_api', false)) {
            abort(404);
        }

        return $next($request);
    }
}
