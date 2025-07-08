<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Fixometer;

class AdminMiddleware
{
    /**
     * Handle an incoming request to ensure user is an administrator.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            return redirect()->guest(route('login'));
        }

        if (!Fixometer::hasRole($user, 'Administrator')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrator access required'
                ], 403);
            }
            abort(403, 'Administrator access required');
        }

        return $next($request);
    }
} 