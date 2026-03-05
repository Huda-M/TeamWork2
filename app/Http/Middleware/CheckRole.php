<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in'
            ], 401);
        }

        if (!in_array(auth()->user()->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource',
                'required_role' => $roles
            ], 403);
        }

        return $next($request);
    }
}
