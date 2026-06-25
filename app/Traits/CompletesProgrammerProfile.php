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
                return response()->json([
                    'success' => false,
                    'message' => 'Only programmers can complete profile'
                ], 403);
            }
            
            $programmer = $user->programmer;
            
            if (!$programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found'
                ], 404);
            }

            if ($programmer->profile_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile already completed'
                ], 400);
            }

            // ✅ بس track و experience_level
            $validated = $request->validate([
                'track' => 'required|string',
                'experience_level' => 'required|in:beginner,junior,intermediate,senior,advanced,expert',
            ]);

            $pointsMap = [
                'beginner'     => 0,
                'junior'       => 50,
                'intermediate' => 100,
                'senior'       => 200,
                'advanced'     => 350,
                'expert'       => 500,
            ];

            $programmer->update([
                'track' => $validated['track'],
                'experience_level' => $validated['experience_level'],
                'total_score' => $pointsMap[$validated['experience_level']] ?? 0,
                'profile_completed' => true,
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Profile completed successfully',
                'data' => [
                    'id' => $programmer->id,
                    'user_name' => $programmer->user_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'track' => $programmer->track,
                    'experience_level' => $programmer->experience_level,
                    'total_score' => $programmer->total_score,
                    'github_username' => $programmer->github_username,
                    'avatar_url' => $programmer->avatar_url,
                    'profile_completed' => true,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Profile completion error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
