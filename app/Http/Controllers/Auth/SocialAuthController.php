<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuth;
use App\Models\Programmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class SocialAuthController extends Controller
{
    public function handleGoogleMobile(Request $request)
    {
        $key = 'google-login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for Google login', [
                'ip' => $request->ip(),
                'retry_after' => $seconds
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Try again after ' . $seconds . ' seconds'
            ], 429);
        }

        try {
            $request->validate([
                'access_token' => [
                    'required',
                    'string',
                    'min:20',
                    'max:2048',
                ],
            ]);

            // ✅ التحقق من الـ token مع Google
            $googleValidation = $this->validateGoogleToken($request->access_token);
            
            if (!$googleValidation['valid']) {
                Log::warning('Invalid Google token', [
                    'ip' => $request->ip(),
                    'error' => $googleValidation['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // ✅ جلب بيانات المستخدم من Google
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            if (!$googleUser || !$googleUser->getEmail()) {
                Log::warning('Google user has no email', [
                    'ip' => $request->ip()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Google account has no email'
                ], 400);
            }

            $email = $googleUser->getEmail();
            
            if (!$this->isAllowedEmail($email)) {
                Log::warning('Blocked email domain', [
                    'ip' => $request->ip(),
                    'email' => $email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email domain not allowed'
                ], 403);
            }

            $result = $this->processGoogleUser($googleUser);

            Log::info('Google login successful', [
                'user_id' => $result['user_data']['user_id'],
                'programmer_id' => $result['user_data']['id'],
                'email' => $email,
                'ip' => $request->ip()
            ]);

            RateLimiter::hit($key);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'user' => $result['user_data'],
                    'role' => 'programmer',
                    'profile_completed' => $result['profile_completed'],
                    'needs_profile_completion' => !$result['profile_completed'],
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'ip' => $request->ip(),
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Google mobile error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'System error'
            ], 500);
        }
    }

    private function validateGoogleToken(string $token): array
    {
        try {
            // ✅ التحقق من الـ token مع Google API
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'access_token' => $token,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // ✅ التحقق من الـ client_id
                if (isset($data['aud']) && $data['aud'] === config('services.google.client_id')) {
                    return ['valid' => true, 'data' => $data];
                }
                return ['valid' => false, 'error' => 'Invalid client_id'];
            }

            return [
                'valid' => false,
                'error' => $response->json()['error_description'] ?? 'Invalid token'
            ];

        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    private function processGoogleUser($googleUser)
    {
        DB::beginTransaction();

        try {
            $email = $googleUser->getEmail();
            $name = $googleUser->getName() ?? 'User';
            $avatar = $googleUser->getAvatar();
            $googleId = $googleUser->getId();

            $existingAuth = UserAuth::where('provider_type', 'google')
                ->where('provider_user_id', $googleId)
                ->first();

            if ($existingAuth) {
                $user = $existingAuth->user;
                Log::info('Existing Google user login', [
                    'user_id' => $user->id,
                    'google_id' => $googleId
                ]);
            } else {
                $user = User::where('email', $email)->first();

                if (!$user) {
                    $user = User::create([
                        'full_name' => $name,
                        'email' => $email,
                        'password' => Hash::make(Str::random(24)),
                        'role' => 'programmer',
                        'email_verified_at' => now(),
                    ]);

                    Programmer::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'user_name' => null,
                            'avatar_url' => $avatar,
                            'github_username' => null, // Google مفيش github_username
                            'bio' => null,
                            'track' => null,
                            'experience_level' => null,
                            'profile_completed' => false,
                        ]
                    );

                    Log::info('New Google user created', [
                        'user_id' => $user->id,
                        'email' => $email
                    ]);
                }
            }

            if ($user->role !== 'programmer') {
                throw new \Exception('This account is registered as ' . $user->role);
            }

            Programmer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'avatar_url' => $avatar ?? $user->programmer?->avatar_url,
                    'bio' => $user->programmer?->bio,
                    'track' => $user->programmer?->track,
                    'experience_level' => $user->programmer?->experience_level,
                    'profile_completed' => $user->programmer?->profile_completed ?? false,
                ]
            );

            $user->refresh();
            $this->storeGoogleAuth($user, $googleUser);

            $token = $user->createToken('google_auth')->plainTextToken;
            $user->load('programmer');
            $programmer = $user->programmer;
            $profileCompleted = $programmer ? $programmer->profile_completed : false;

            $userData = [
                'id' => $programmer?->id,
                'user_id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => 'programmer',
                'avatar' => $programmer?->avatar_url ?? null,
                'github_username' => $programmer?->github_username ?? null,
                'profile_completed' => $profileCompleted,
            ];

            DB::commit();

            return [
                'token' => $token,
                'user_data' => $userData,
                'profile_completed' => $profileCompleted,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function storeGoogleAuth(User $user, $googleUser)
    {
        UserAuth::updateOrCreate(
            ['user_id' => $user->id, 'provider_type' => 'google'],
            [
                'user_id' => $user->id,
                'provider_type' => 'google',
                'provider_user_id' => $googleUser->getId(),
                'provider_email' => $googleUser->getEmail(),
                'provider_name' => $googleUser->getName(),
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken ?? null,
                'token_expires_at' => $googleUser->expiresIn ? now()->addSeconds($googleUser->expiresIn) : null,
            ]
        );
    }

    public function handleGitHubMobile(Request $request)
    {
        $key = 'github-login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for GitHub login', [
                'ip' => $request->ip(),
                'retry_after' => $seconds
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Try again after ' . $seconds . ' seconds'
            ], 429);
        }

        try {
            $request->validate([
                'access_token' => [
                    'required',
                    'string',
                    'min:10',
                    'max:255',
                    'regex:/^gho_|ghp_|github_pat_/i'
                ],
            ]);

            $githubValidation = $this->validateGitHubToken($request->access_token);
            
            if (!$githubValidation['valid']) {
                Log::warning('Invalid GitHub token', [
                    'ip' => $request->ip(),
                    'error' => $githubValidation['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            $githubUser = Socialite::driver('github')
                ->stateless()
                ->userFromToken($request->access_token);

            if (!$githubUser || !$githubUser->getEmail()) {
                Log::warning('GitHub user has no email', [
                    'ip' => $request->ip(),
                    'github_id' => $githubUser?->getId()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'GitHub account has no public email. Please enable email in GitHub settings'
                ], 400);
            }

            $email = $githubUser->getEmail();
            
            if (!$this->isAllowedEmail($email)) {
                Log::warning('Blocked email domain', [
                    'ip' => $request->ip(),
                    'email' => $email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email domain not allowed'
                ], 403);
            }

            $result = $this->processGitHubUser($githubUser);

            Log::info('GitHub login successful', [
                'user_id' => $result['user_data']['user_id'],
                'programmer_id' => $result['user_data']['id'],
                'email' => $email,
                'ip' => $request->ip()
            ]);

            RateLimiter::hit($key);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'user' => $result['user_data'],
                    'role' => 'programmer',
                    'profile_completed' => $result['profile_completed'],
                    'needs_profile_completion' => !$result['profile_completed'],
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'ip' => $request->ip(),
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('GitHub mobile error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'System error'
            ], 500);
        }
    }

    /**
     * ✅ التحقق من الـ Token مع GitHub API
     */
    private function validateGitHubToken(string $token): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
            ])->get('https://api.github.com/user');

            if ($response->successful()) {
                return ['valid' => true, 'data' => $response->json()];
            }

            return [
                'valid' => false,
                'error' => $response->json()['message'] ?? 'Invalid token'
            ];

        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ✅ التحقق من الـ Email Domain
     */
    private function isAllowedEmail(string $email): bool
    {
        $blockedDomains = [
            'tempmail.com',
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
        ];

        $domain = substr(strrchr($email, "@"), 1);
        
        return !in_array($domain, $blockedDomains);
    }

   private function processGitHubUser($githubUser)
{
    DB::beginTransaction();

    try {
        $email = $githubUser->getEmail();
        $name = $githubUser->getName() ?? $githubUser->getNickname() ?? 'Developer';
        $avatar = $githubUser->getAvatar();
        $githubUsername = $githubUser->getNickname();
        $githubId = $githubUser->getId();

        $existingAuth = UserAuth::where('provider_type', 'github')
            ->where('provider_user_id', $githubId)
            ->first();

        if ($existingAuth) {
            $user = $existingAuth->user;
            Log::info('Existing GitHub user login', [
                'user_id' => $user->id,
                'github_id' => $githubId
            ]);
        } else {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'full_name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'programmer',
                    'email_verified_at' => now(),
                ]);

                // ✅ user_name = null
                Programmer::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'user_name' => null,
                        'avatar_url' => $avatar,
                        'github_username' => $githubUsername,
                        'bio' => null,
                        'track' => null,
                        'experience_level' => null,
                        'profile_completed' => false,
                    ]
                );

                Log::info('New GitHub user created', [
                    'user_id' => $user->id,
                    'email' => $email
                ]);
            }
        }

        if ($user->role !== 'programmer') {
            throw new \Exception('This account is registered as ' . $user->role);
        }

        Programmer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'avatar_url' => $avatar ?? $user->programmer?->avatar_url,
                'github_username' => $githubUsername ?? $user->programmer?->github_username,
                'bio' => $user->programmer?->bio,
                'track' => $user->programmer?->track,
                'experience_level' => $user->programmer?->experience_level,
                'profile_completed' => $user->programmer?->profile_completed ?? false,
            ]
        );

        $user->refresh();
        $this->storeGitHubAuth($user, $githubUser);

        $token = $user->createToken('github_auth')->plainTextToken;
        $user->load('programmer');
        $programmer = $user->programmer;
        $profileCompleted = $programmer ? $programmer->profile_completed : false;

        $userData = [
            'id' => $programmer?->id,
            'user_id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => 'programmer',
            'avatar' => $programmer?->avatar_url ?? null,
            'github_username' => $programmer?->github_username ?? null,
            'profile_completed' => $profileCompleted,
        ];

        DB::commit();

        return [
            'token' => $token,
            'user_data' => $userData,
            'profile_completed' => $profileCompleted,
        ];

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
    // ─── Helpers ───

    private function storeGitHubAuth(User $user, $githubUser)
    {
        UserAuth::updateOrCreate(
            ['user_id' => $user->id, 'provider_type' => 'github'],
            [
                'user_id' => $user->id,
                'provider_type' => 'github',
                'provider_user_id' => $githubUser->getId(),
                'provider_email' => $githubUser->getEmail(),
                'provider_name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'access_token' => $githubUser->token,
                'refresh_token' => $githubUser->refreshToken ?? null,
                'token_expires_at' => $githubUser->expiresIn ? now()->addSeconds($githubUser->expiresIn) : null,
            ]
        );
    }

    // ─── Web OAuth (redirect-based) ───

    /**
     * Redirect to the OAuth provider.
     */
    public function redirectToProvider($provider)
    {
        $allowed = ['google', 'github', 'facebook'];
        if (! in_array($provider, $allowed)) {
            return response()->json(['message' => 'Unsupported provider'], 422);
        }

        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle the OAuth provider callback.
     * Google/GitHub/Facebook redirect back here after user grants access.
     */
    public function handleProviderCallback($provider)
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            Log::error("OAuth callback error [{$provider}]: " . $e->getMessage());
            return redirect($frontendUrl . '/auth/callback?error=' . urlencode('Authentication failed'));
        }

        DB::beginTransaction();
        try {
            $userAuth = UserAuth::where('provider_type', $provider)
                ->where('provider_user_id', $socialUser->getId())
                ->first();

            $user = $userAuth ? $userAuth->user : null;

            if ($userAuth && ! $user) {
                $userAuth->delete();
                $userAuth = null;
            }

            $isNew = false;

            if (! $userAuth) {
                $user = User::where('email', $socialUser->getEmail())->first();

                if (! $user) {
                    $user = User::create([
                        'full_name'         => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Unknown User',
                        'email'             => $socialUser->getEmail(),
                        'password'          => Hash::make(Str::random(24)),
                        'role'              => 'programmer',
                        'email_verified_at' => now(),
                    ]);

                    Programmer::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'user_name'        => null,
                            'avatar_url'       => $socialUser->getAvatar(),
                            'github_username'  => $provider === 'github' ? $socialUser->getNickname() : null,
                            'bio'              => null,
                            'track'            => null,
                            'experience_level' => null,
                            'profile_completed'=> false,
                        ]
                    );

                    $isNew = true;
                }

                UserAuth::updateOrCreate(
                    ['user_id' => $user->id, 'provider_type' => $provider],
                    [
                        'user_id'          => $user->id,
                        'provider_type'    => $provider,
                        'provider_user_id' => $socialUser->getId(),
                        'provider_email'   => $socialUser->getEmail(),
                        'provider_name'    => $socialUser->getName() ?? $socialUser->getNickname(),
                        'access_token'     => $socialUser->token,
                        'refresh_token'    => $socialUser->refreshToken ?? null,
                        'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    ]
                );
            }

            DB::commit();

            $token = $user->createToken('social_auth')->plainTextToken;

            return redirect(
                $frontendUrl . '/auth/callback?token=' . $token
                . '&is_new=' . ($isNew ? 'true' : 'false')
                . '&role=' . $user->role
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("OAuth processing error [{$provider}]: " . $e->getMessage());
            return redirect($frontendUrl . '/auth/callback?error=' . urlencode('Authentication failed'));
        }
    }

    /**
     * Complete social registration (called after user fills profile info).
     */
    public function completeSocialRegistration(Request $request)
    {
        $request->validate([
            'token'     => 'required|string',
            'user_name' => 'required|string|max:50|unique:programmers,user_name',
        ]);

        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $programmer = Programmer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_name'        => $request->user_name,
                'profile_completed'=> true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile completed',
            'user'    => $user->load('programmer'),
        ]);
    }
}
