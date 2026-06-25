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

class SocialAuthController extends Controller
{
    /**
     * ✅ MOBILE ONLY: استقبال GitHub Token من Flutter
     * POST /api/auth/github/mobile
     */
    public function handleGitHubMobile(Request $request)
    {
        try {
            $request->validate([
                'access_token' => 'required|string',
            ]);

            $githubUser = Socialite::driver('github')
                ->stateless()
                ->userFromToken($request->access_token);

            if (!$githubUser || !$githubUser->getEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'التوكن غير صالح أو الـ GitHub account مفيهوش email public'
                ], 401);
            }

            $result = $this->processGitHubUser($githubUser);

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

        } catch (\Exception $e) {
            Log::error("GitHub mobile error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في التسجيل',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔧 CORE: معالجة بيانات GitHub (للمبرمجين فقط)
     */
    private function processGitHubUser($githubUser)
    {
        DB::beginTransaction();

        try {
            $email = $githubUser->getEmail();
            $name = $githubUser->getName() ?? $githubUser->getNickname() ?? 'Developer';
            $avatar = $githubUser->getAvatar();
            $githubUsername = $githubUser->getNickname();

            // ✅ البحث عن مستخدم موجود
            $user = User::where('email', $email)->first();

            if (!$user) {
                // ✅ مستخدم جديد تماماً
                $user = User::create([
                    'full_name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'programmer',
                    'email_verified_at' => now(),
                ]);

                // ✅ إنشاء بروفايل المبرمج باستخدام updateOrCreate
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

            } else {
                // ✅ مستخدم موجود: تأكدي من الدور
                if ($user->role !== 'programmer') {
                    throw new \Exception('هذا الحساب مسجل كـ ' . $user->role);
                }

                // ✅ استخدمي updateOrCreate بدل ما تتحققي يدوياً
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

                // ✅ أعدي تحميل العلاقة
                $user->refresh();
            }

            // ✅ حفظ بيانات GitHub
            $this->storeGitHubAuth($user, $githubUser);

            // ✅ إنشاء Sanctum token
            $token = $user->createToken('github_auth')->plainTextToken;

            // ✅ أعدي تحميل العلاقة بعد الإنشاء/التحديث
            $user->load('programmer');
            $programmer = $user->programmer;
            $profileCompleted = $programmer ? $programmer->profile_completed : false;

            $userData = [
                'id' => $programmer?->id,           // ✅ programmer_id بدل user_id
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

    /**
     * ✅ إكمال بروفايل المبرمج
     * POST /api/auth/complete-profile
     */
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
                'track' => 'required|string|max:100',
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
                    'bio' => $programmer->bio,
                    'github_username' => $programmer->github_username,
                    'avatar_url' => $programmer->avatar_url,
                    'profile_completed' => true,
                ]
            ]);

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
