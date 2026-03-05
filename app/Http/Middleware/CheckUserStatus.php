<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->role === 'admin') {
                return $next($request);
            }

            if ($user->is_banned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been permanently banned',
                    'data' => [
                        'status' => 'banned',
                        'banned_at' => $user->banned_at,
                    ]
                ], 403);
            }

            if ($user->is_suspended && $user->suspended_until && now()->lt($user->suspended_until)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is suspended until ' . $user->suspended_until->format('Y-m-d H:i:s'),
                    'data' => [
                        'status' => 'suspended',
                        'suspended_until' => $user->suspended_until,
                        'days_remaining' => now()->diffInDays($user->suspended_until),
                    ]
                ], 403);
            }

            if ($user->is_suspended && $user->suspended_until && now()->gte($user->suspended_until)) {
                $user->update([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
            }
        }

        return $next($request);
    }
}
