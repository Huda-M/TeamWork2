<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirectToProvider(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'url' => $url,
        ]);
    }

    public function handleProviderCallback(string $provider, Request $request): JsonResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            Log::error('Social auth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to authenticate with ' . $provider,
                'error' => $e->getMessage(),
            ], 400);
        }

        $userAuth = UserAuth::where('provider_type', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($userAuth) {
            $user = $userAuth->user;

            $userAuth->update([
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            Auth::login($user);

            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'profile_completed' => $user->profile_completed,
            ], 200);
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            DB::beginTransaction();
            try {
                UserAuth::create([
                    'user_id' => $user->id,
                    'provider_type' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                    'provider_email' => $socialUser->getEmail(),
                    'provider_name' => $socialUser->getName(),
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                ]);

                DB::commit();

                Auth::login($user);

                return response()->json([
                    'message' => 'Social account linked successfully',
                    'user' => $user,
                    'token' => $user->createToken('auth_token')->plainTextToken,
                    'profile_completed' => $user->profile_completed,
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to link social account', [
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'message' => 'Failed to link social account',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'role' => 'programmer',
                'avatar_url' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
            ]);

            UserAuth::create([
                'user_id' => $user->id,
                'provider_type' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $socialUser->getName(),
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            DB::commit();

            Auth::login($user);

            Log::info('New user registered via social auth', [
                'user_id' => $user->id,
                'provider' => $provider,
                'email' => $user->email
            ]);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'profile_completed' => false,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user from social auth', [
                'provider' => $provider,
                'email' => $socialUser->getEmail(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create user account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeSocialRegistration(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'in:company,programmer'],
            'provider' => ['required', 'in:google,facebook,github'],
            'provider_user_id' => ['required', 'string'],
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'avatar' => ['nullable', 'string'],
            'token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'expires_in' => ['nullable', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'avatar_url' => $request->avatar,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
            ]);

            UserAuth::create([
                'user_id' => $user->id,
                'provider_type' => $request->provider,
                'provider_user_id' => $request->provider_user_id,
                'provider_email' => $request->email,
                'provider_name' => $request->name,
                'access_token' => $request->token,
                'refresh_token' => $request->refresh_token,
                'token_expires_at' => $request->expires_in ? now()->addSeconds($request->expires_in) : null,
            ]);

            DB::commit();

            Auth::login($user);

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'profile_completed' => false,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete social registration', [
                'email' => $request->email,
                'provider' => $request->provider,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to complete registration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'facebook', 'github'])) {
            abort(404, 'Invalid provider');
        }
    }
}
