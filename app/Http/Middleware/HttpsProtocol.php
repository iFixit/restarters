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

        // This is meant for local testing of the production image where APP_DEBUG can be set to true
        if (env('APP_ENV') === 'production' && env('APP_DEBUG') === true) {
            $host = $request->getHost();
            if (in_array($host, ['localhost', '127.0.0.1', '::1']) || str_contains($host, '.local')) {
                \URL::forceScheme('http');
                return $next($request);
            }
        }

        if (! $request->secure() && (env('APP_ENV') === 'development' || env('APP_ENV') === 'production')) {
            // Force URL generation to use HTTPS
            \URL::forceScheme('https');
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
