<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

class HttpsProtocol
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If we're behind a proxy that sets X-Forwarded-Proto
        if ($request->header('X-Forwarded-Proto') === 'https') {
            // Force URL generation to use HTTPS
            \URL::forceScheme('https');
            return $next($request);
        }

        if (! $request->secure() && (env('APP_ENV') === 'development' || env('APP_ENV') === 'production')) {
            // Force URL generation to use HTTPS
            \URL::forceScheme('https');
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
