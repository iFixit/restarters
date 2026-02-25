<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return $this->unauthorized();
        }

        $client = ApiClient::where('token_hash', hash('sha256', $bearerToken))->first();

        if (!$client || !$client->active || $client->hasExpired()) {
            return $this->unauthorized();
        }

        if ($requiredScope && !$client->hasScope($requiredScope)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $request->attributes->set('apiClient', $client);

        $client->last_used_at = now();
        $client->save();

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
