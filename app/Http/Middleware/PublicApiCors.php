<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response()->noContent();
            return $this->addHeaders($request, $response);
        }

        $response = $next($request);
        return $this->addHeaders($request, $response);
    }

    private function addHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';

        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        $response->headers->set('Access-Control-Max-Age', '3600');
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }
}
