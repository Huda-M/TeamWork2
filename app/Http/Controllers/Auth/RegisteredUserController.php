<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{

public function register(Request $request): JsonResponse
{
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
        'role' => ['required', 'in:company,programmer,admin'],
    ]);

    cache()->put('registration:' . $request->email, [
        'name' => $request->name,
        'password' => $request->password,
        'role' => $request->role,
    ], now()->addMinutes(30));

    EmailVerificationCode::createForEmail($request->email);

    return response()->json([
        'message' => 'Verification code sent to your email',
        'email' => $request->email,
    ], 200);
}


public function verifyAndCreate(Request $request): JsonResponse
{
    $request->validate([
        'email' => ['required', 'email'],
        'code' => ['required', 'string', 'size:6'],
    ]);

    if (!EmailVerificationCode::verify($request->email, $request->code)) {
        throw ValidationException::withMessages([
            'code' => ['Invalid or expired verification code.'],
        ]);
    }


    $registrationData = cache()->get('registration:' . $request->email);

    if (!$registrationData) {
        return response()->json([
            'message' => 'Registration data expired. Please register again.',
        ], 400);
    }

    $user = User::create([
        'name' => $registrationData['name'],
        'email' => $request->email,
        'password' => Hash::make($registrationData['password']),
        'role' => $registrationData['role'],
        'email_verified_at' => now(),
    ]);

    event(new Registered($user));

    cache()->forget('registration:' . $request->email);

    Auth::login($user);

    return response()->json([
        'message' => 'Registration successful',
        'user' => $user,
        'token' => $user->createToken('auth_token')->plainTextToken,
        'profile_completed' => false,
    ], 201);
}


    public function completeProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name,' . $user->id],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'avatar_url' => ['nullable', 'url'],
            'behance_url' => ['nullable', 'url'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update($validated);
        $user->markProfileAsCompleted();

        return response()->json([
            'message' => 'Profile completed successfully',
            'user' => $user->fresh(),
        ], 200);
    }

    public function resendCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        EmailVerificationCode::createForEmail($request->email);

        return response()->json([
            'message' => 'Verification code resent',
        ], 200);
    }


    public function profileStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'profile_completed' => $user->profile_completed,
            'is_profile_complete' => $user->isProfileCompleted(),
            'user' => $user,
        ], 200);
    }
}
