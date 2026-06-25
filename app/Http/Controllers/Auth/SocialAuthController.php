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

    /**
     * 🔧 CORE: معالجة بيانات GitHub
     */
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

                    Programmer::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'user_name' => $this->generateUniqueUsername($githubUser),
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
                    'user_name' => $this->generateUniqueUsername($githubUser),
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
}
