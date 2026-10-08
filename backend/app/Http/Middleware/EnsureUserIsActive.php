<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject requests from accounts that are pending approval or deactivated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->activo) {
            return response()->json([
                'message' => 'Tu cuenta no está activa.',
            ], 403);
        }

        return $next($request);
    }
}
