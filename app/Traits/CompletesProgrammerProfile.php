<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait CompletesProgrammerProfile
{
   public function completeProfile(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'programmer') {
                return response()->json(['message' => 'Only programmers can complete profile'], 403);
            }
            $programmer = $user->programmer;
            if (!$programmer) {
                return response()->json(['message' => 'Programmer profile not found'], 404);
            }

            $validated = $request->validate([
                'experience_level' => 'required|in:beginner,junior,intermediate,senior,advanced,expert',
                'track' => 'required|string',
            ]);

            $pointsMap = [
                'beginner'    => 0,
                'junior'      => 50,
                'intermediate'=> 100,
                'senior'      => 200,
                'advanced'    => 350,
                'expert'      => 500,
            ];

            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $validated['avatar_url'] = $path;
            }

            $validated['total_score'] = $pointsMap[$validated['experience_level']] ?? 0;
            $validated['profile_completed'] = true;

            $programmer->update($validated);

            return response()->json(['success' => true, 'message' => 'Profile completed']);
        } catch (\Exception $e) {
            Log::error('Profile completion error: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }
}
