<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicRepairsApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('restarters.features.public_repairs_api', false)) {
            abort(404);
        }

        return $next($request);
    }
}
