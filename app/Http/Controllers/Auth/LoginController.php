<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email',
            'password'  => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();

            if (is_null($user->email_verified_at)) {
                Auth::logout();
                return response()->json([
                    'message' => 'Your account is not activated. Please activate it using the code sent to your email.',
                    'needs_verification' => true,
                    'email' => $user->email
                ], 403);
            }

            if ($request->has('fcm_token')) {
                $user->update(['fcm_token' => $request->fcm_token]);
            }

            // جلب بيانات الـ Programmer المرتبط
            $programmer = $user->programmer;

            // حساب is_completed: لازم يكون فيه user_name و track و bio
            $isCompleted = $programmer && 
                          !empty($programmer->user_name) && 
                          !empty($programmer->track) && 
                          !empty($programmer->bio);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'user' => [
                    'id'            => $user->id,
                    'name'          => $user->full_name,           // ← من users.full_name
                    'email'         => $user->email,
                    'user_name'     => $programmer?->user_name,    // ← من programmers.user_name
                    'role'          => $user->role,
                    'avatar_url'    => $programmer?->avatar_url 
                                        ? Storage::disk('public')->url($programmer->avatar_url) 
                                        : null,
                    
                    'is_verified'   => !is_null($user->email_verified_at),
                    'fcm_token'     => $user->fcm_token,
                    'is_completed'  => $isCompleted,              // ← جديد
                    'track'         => $programmer?->track,        // ← جديد (مفيد للـ frontend)
                    'bio'           => $programmer?->bio,        // ← جديد
                ],
                'token'      => $token,
                'token_type' => 'Bearer'
            ], 200);
        }

        return response()->json(['message' => 'Incorrect email or password.'], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Successful logout.'], 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.'], 200);
    }
}
