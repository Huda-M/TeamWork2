<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{

    public function verifyResetCode(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
        'code' => 'required|string|size:6',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $resetCode = PasswordResetCode::verify($request->email, $request->code);

    if (!$resetCode) {
        return response()->json([
            'message' => 'The verification code is invalid or expired.'
        ], 400);
    }

    $resetToken = Str::random(60);
    cache()->put('password_reset:' . $resetToken, $request->email, now()->addMinutes(15));

    return response()->json([
        'message' => 'Verification code is correct.',
        'reset_token' => $resetToken,
        'expires_at' => now()->addMinutes(15)->toDateTimeString()
    ], 200);
}


    public function store(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'reset_token' => 'required|string',
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $email = cache()->get('password_reset:' . $request->reset_token);

    if (!$email) {
        return response()->json([
            'message' => 'The reset code is invalid or expired.'
        ], 400);
    }
    $user = User::where('email', $email)->first();
    $user->password = Hash::make($request->password);
    $user->save();

    PasswordResetCode::deleteCode($email);

    cache()->forget('password_reset:' . $request->reset_token);

    return response()->json([
        'message' => 'The password has been successfully changed.'
    ], 200);
}

public function changePassword(Request $request): JsonResponse
{
    $user = $request->user();

    $validator = Validator::make($request->all(), [
        'current_password' => ['required', 'string'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'The current password is incorrect.'], 400);
    }

    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json(['message' => 'Password changed successfully.'], 200);
}
}
