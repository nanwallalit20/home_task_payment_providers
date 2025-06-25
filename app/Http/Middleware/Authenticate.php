<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API routes, return null to avoid redirects and trigger JSON response
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return $request->expectsJson() ? null : route('login');
    }

    /**
     * Handle unauthenticated users for API requests
     */
    protected function unauthenticated($request, array $guards)
    {
        // For API routes, return JSON error response
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(response()->json([
                'message' => 'Unauthenticated.',
                'error' => 'Token Expired or Invalid. Please login to access this resource.'
            ], 401));
        }

        parent::unauthenticated($request, $guards);
    }
}
