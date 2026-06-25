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
    /**
     * ✅ MOBILE ONLY: استقبال GitHub Token من Flutter
     * POST /api/auth/github/mobile
     */
    public function handleGitHubMobile(Request $request)
    {
        // ✅ 1. Rate Limiting Check
        $key = 'github-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for GitHub login', [
                'ip' => $request->ip(),
                'retry_after' => $seconds
            ]);
            return response()->json([
                'success' => false,
                'message' => 'محاولات كتيرة. جربي تاني بعد ' . $seconds . ' ثانية'
            ], 429);
        }

        try {
            $request->validate([
                'access_token' => [
                    'required',
                    'string',
                    'min:10',           // ✅ التحقق من الطول
                    'max:255',
                    'regex:/^gho_|ghp_|github_pat_/i'  // ✅ يبدأ بـ gho_ أو ghp_ أو github_pat_
                ],
            ]);

            // ✅ 2. التحقق من الـ token مع GitHub API مباشرة
            $githubValidation = $this->validateGitHubToken($request->access_token);
            
            if (!$githubValidation['valid']) {
                Log::warning('Invalid GitHub token', [
                    'ip' => $request->ip(),
                    'error' => $githubValidation['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'التوكن غير صالح أو منتهي'
                ], 401);
            }

            // ✅ 3. جلب بيانات المستخدم من GitHub
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
                    'message' => 'الـ GitHub account مفيهوش email public. فعلي الـ email في إعدادات GitHub'
                ], 400);
            }

            // ✅ 4. التحقق من الـ Email Domain (اختياري)
            $email = $githubUser->getEmail();
            if (!$this->isAllowedEmail($email)) {
                Log::warning('Blocked email domain', [
                    'ip' => $request->ip(),
                    'email' => $email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'الـ email domain غير مسموح بيه'
                ], 403);
            }

            $result = $this->processGitHubUser($githubUser);

            // ✅ 5. تسجيل النجاح
            Log::info('GitHub login successful', [
                'user_id' => $result['user_data']['user_id'],
                'programmer_id' => $result['user_data']['id'],
                'email' => $email,
                'ip' => $request->ip()
            ]);

            RateLimiter::hit($key);

            return response()->json([
                'success' => true,
                'message' => 'تم التسجيل بنجاح',
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
                'message' => 'البيانات غير صحيحة',
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
                'message' => 'فشل في التسجيل',
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في النظام'
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
        // ✅ سمحي بكل الـ domains أو حددي list
        $blockedDomains = [
            'tempmail.com',
            '10minutemail.com',
            'guerrillamail.com',
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

            // ✅ التحقق من عدم وجود GitHub ID تاني
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
                            'profile_completed' => false,
                        ]
                    );

                    Log::info('New GitHub user created', [
                        'user_id' => $user->id,
                        'email' => $email
                    ]);
                }
            }

            // ✅ تحديث البيانات
            if ($user->role !== 'programmer') {
                throw new \Exception('هذا الحساب مسجل كـ ' . $user->role);
            }

            Programmer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'user_name' => $this->generateUniqueUsername($githubUser),
                    'avatar_url' => $avatar ?? $user->programmer?->avatar_url,
                    'github_username' => $githubUsername ?? $user->programmer?->github_username,
                    'bio' => $user->programmer?->bio,
                    'track' => $user->programmer?->track,
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

   public function completeProfile(Request $request)
{
    try {
        $user = $request->user();

        if (!$user || $user->role !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح'
            ], 403);
        }

        $programmer = $user->programmer;

        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'البروفايل مش موجود'
            ], 404);
        }

        if ($programmer->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'البروفايل مكتمل بالفعل'
            ], 400);
        }

        $validated = $request->validate([
            'user_name' => 'required|string|max:255|unique:programmers,user_name,' . $programmer->id,
            'phone' => 'required|string|max:20',
            // ✅ track محدد مسبقاً
            'track' => 'required|string|in:Web Development,Mobile Development,AI & Data Science,DevOps,UI/UX Design,Game Development,Cybersecurity,Blockchain,Cloud Computing',
            // ✅ experience_level محدد مسبقاً
            'experience_level' => 'required|string|in:beginner,junior,senior,expert',
            'bio' => 'nullable|string|max:1000',
        ]);

        $programmer->update([
            ...$validated,
            'profile_completed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إكمال البروفايل بنجاح',
            'data' => [
                'id' => $programmer->id,
                'user_name' => $programmer->user_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $programmer->phone,
                'track' => $programmer->track,
                'experience_level' => $programmer->experience_level,
                'bio' => $programmer->bio,
                'github_username' => $programmer->github_username,
                'avatar_url' => $programmer->avatar_url,
                'profile_completed' => true,
            ]
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'البيانات غير صحيحة',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('Complete profile error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'فشل في إكمال البروفايل'
        ], 500);
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

    private function generateUniqueUsername($githubUser)
    {
        $base = $githubUser->getNickname()
            ?? explode('@', $githubUser->getEmail())[0]
            ?? 'dev_' . Str::random(5);

        $username = Str::slug($base);
        $counter = 1;

        while (Programmer::where('user_name', $username)->exists()) {
            $username = Str::slug($base) . '_' . $counter++;
        }

        return $username;
    }
}
