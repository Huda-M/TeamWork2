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
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'user_name';

        $request->merge([$loginType => $request->login]);

        if (Auth::attempt($request->only($loginType, 'password'))) {
            $user = Auth::user();

            if (is_null($user->email_verified_at)) {
                Auth::logout();
                return response()->json([
                    'message' => 'Your account is not activated. Please activate it using the code sent to your email.',
                    'needs_verification' => true,
                    'email' => $user->email
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_name' => $user->user_name,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar_url,
                    'country' => $user->country,
                    'phone' => $user->phone,
                    'is_verified' => !is_null($user->email_verified_at),
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ], 200);
        }

        return response()->json(['message' => 'Incorrect login data.'], 401);
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
        return response()->json(['message' => 'User is not found.'], 404);
    }

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'The current password is incorrect.'], 400);
    }

    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json(['message' => 'The password has been successfully changed.'], 200);
}
}
