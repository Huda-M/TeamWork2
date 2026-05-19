<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Auth\ChangePasswordRequest;
use App\Http\Requests\Company\Auth\LoginRequest;
use App\Http\Requests\Company\Auth\RegisterRequest;
use App\Models\User;
use App\Models\UserAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {

        $validate = $request->validated();

        if (! Auth::attempt($validate)) {
            return response()->json([
                'message' => 'Email or password is invalid',
            ],401);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->role !== 'company') {
            return response()->json([
                'message' => 'You are not authorized to login as company',
                'status' => 403,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout()
    {

        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successfully',
            'status' => 200,
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['role'] = 'company';
        $user = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Register successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();

        $user = auth()->user();
        if (! Hash::check($data['old_password'], $user->password)) {
            return response()->json([
                'message' => 'Old password is incorrect',
                'status' => 401,
            ]);
        }
        $user->update($data);

        return response()->json([
            'message' => 'Password changed successfully',
            'status' => 200,
            'user' => $user->load('company'),
        ]);
    }

    public function redirectToProvider($provider)
    {
        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl()
        ]);
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to authenticate using ' . ucfirst($provider),
                'status' => 401,
            ]);
        }

        $userAuth = UserAuth::where('provider_type', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($userAuth) {
            $user = $userAuth->user;

            if ($user->role !== 'company') {
                return response()->json([
                    'message' => 'You are not authorized to login as company',
                    'status' => 403,
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successfully',
                'status' => 200,
                'user' => $user->load('company'),
                'token' => $token,
            ]);
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            if ($user->role !== 'company') {
                return response()->json([
                    'message' => 'You are not authorized to login as company',
                    'status' => 403,
                ]);
            }

            $user->userAuth()->create([
                'provider_type' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);
        } else {
            $user = User::create([
                'full_name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Unknown User',
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(24)),
                'role' => 'company',
            ]);

            $user->userAuth()->create([
                'provider_type' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successfully',
            'status' => 200,
            'user' => $user->load('company'),
            'token' => $token,
        ]);
    }
}
