<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuth;
use App\Models\Programmer;
use App\Models\Company; // تأكد من وجود موديل Company
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * إعادة التوجيه إلى مزود الخدمة (Google, Facebook, GitHub)
     */
    public function redirectToProvider($provider)
    {
        $this->validateProvider($provider);
        return Socialite::driver($provider)->redirect();
    }

    /**
     * معالجة الاستجابة من مزود الخدمة
     */
    public function handleProviderCallback($provider)
    {
        try {
            $this->validateProvider($provider);
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            \Log::error("Social auth error for {$provider}: " . $e->getMessage());
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        DB::beginTransaction();
        try {
            // البحث عن مستخدم بنفس البريد الإلكتروني
            $user = User::where('email', $socialUser->getEmail())->first();

            // ============================================================
            // 1. تحديد الدور بناءً على المزود (Provider)
            // ============================================================
            $role = $this->getRoleByProvider($provider);

            if (!$user) {
                // إنشاء مستخدم جديد بالدور المحدد
                $user = User::create([
                    'full_name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(24)),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]);

                // ============================================================
                // 2. إنشاء الملف الشخصي المناسب حسب الدور
                // ============================================================
                if ($role === 'programmer') {
                    $user->programmer()->create([
                        'user_name' => $this->generateUniqueUsername($socialUser, $provider),
                        'avatar_url' => $socialUser->getAvatar(),
                        'bio' => null,
                        'track' => null,
                        'profile_completed' => false,
                    ]);
                } elseif ($role === 'company') {
                    $user->company()->create([
                        'company_name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'Company',
                        'phone' => null,
                        'cr_number' => 'SOCIAL_' . Str::random(10),
                        'about' => null,
                        'country' => null,
                        'location' => null,
                        'industry' => null,
                        'size' => null,
                        'website' => null,
                        'logo' => $socialUser->getAvatar(),
                        'profile_completed' => false,
                    ]);
                }

            } else {
                // ============================================================
                // 3. إذا كان المستخدم موجوداً: نتحقق من تطابق الدور
                // ============================================================
                // إذا كان المستخدم له دور مختلف عن المزود الحالي، يمكننا تحديثه أو تركه
                // هنا نترك الدور الحالي كما هو (لا نغيره)
                $programmer = $user->programmer;
                if ($programmer && !$programmer->avatar_url && $socialUser->getAvatar()) {
                    $programmer->update(['avatar_url' => $socialUser->getAvatar()]);
                }

                // إذا كان المستخدم شركة، نقوم بتحديث الشعار إذا لم يكن موجوداً
                $company = $user->company;
                if ($company && !$company->logo && $socialUser->getAvatar()) {
                    $company->update(['logo' => $socialUser->getAvatar()]);
                }
            }

            // حفظ بيانات المصادقة (Social Auth Credentials)
            $this->storeOrUpdateSocialAuth($user, $socialUser, $provider);

            // تسجيل الدخول
            Auth::login($user);
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            // إعادة التوجيه إلى الواجهة الأمامية
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

            // تجهيز بيانات المستخدم للـ Frontend
            $userData = [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_completed' => $user->role === 'programmer' 
                    ? ($user->programmer->profile_completed ?? false)
                    : ($user->company->profile_completed ?? false),
            ];

            // إضافة بيانات خاصة حسب الدور
            if ($user->role === 'programmer' && $user->programmer) {
                $userData['avatar'] = $user->programmer->avatar_url;
                $userData['user_name'] = $user->programmer->user_name;
            } elseif ($user->role === 'company' && $user->company) {
                $userData['avatar'] = $user->company->logo;
                $userData['company_name'] = $user->company->company_name;
            }

            return redirect($frontendUrl . '/auth/callback?token=' . $token . '&user=' . urlencode(json_encode($userData)));

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Social auth transaction error: " . $e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * تحديد الدور بناءً على مزود الخدمة
     */
    private function getRoleByProvider($provider)
    {
        $roleMapping = [
            'github'   => 'programmer',
            'facebook' => 'company',
            'google'   => 'programmer', // يمكنك تغييره إلى 'company' أو تركه اختياري
        ];

        return $roleMapping[$provider] ?? 'programmer';
    }



    /**
     * إكمال بيانات التسجيل عبر السوشيال ميديا
     */
    public function completeSocialRegistration(Request $request)
    {
        try {
            $user = $request->user();
            $programmer = $user->programmer;

            if (!$programmer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programmer profile not found'
                ], 404);
            }

            // التحقق من البيانات المدخلة
            $validated = $request->validate([
                'user_name' => 'required|string|max:255|unique:programmers,user_name,' . $programmer->id,
                'phone' => 'required|string|max:20',
                'track' => 'nullable|string|max:100',
                'bio' => 'nullable|string|max:1000',
            ]);

            // تحديث بيانات البرمجة
            $programmer->update($validated);
            $programmer->profile_completed = true;
            $programmer->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile completed successfully',
                'data' => [
                    'id' => $programmer->id,
                    'user_name' => $programmer->user_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $programmer->phone,
                    'track' => $programmer->track,
                    'bio' => $programmer->bio,
                    'avatar_url' => $programmer->avatar_url,
                    'profile_completed' => $programmer->profile_completed,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Complete profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حفظ أو تحديث بيانات المصادقة من السوشيال ميديا
     */
    private function storeOrUpdateSocialAuth(User $user, $socialUser, $provider)
    {
        $userAuth = UserAuth::where('user_id', $user->id)
            ->where('provider_type', $provider)
            ->first();

        $authData = [
            'user_id' => $user->id,
            'provider_type' => $provider,
            'provider_user_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_name' => $socialUser->getName() ?? $socialUser->getNickname(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ];

        if ($userAuth) {
            $userAuth->update($authData);
        } else {
            UserAuth::create($authData);
        }
    }

    /**
     * توليد اسم مستخدم فريد بناءً على بيانات السوشيال ميديا
     */
    private function generateUniqueUsername($socialUser, $provider)
    {
        // حاول استخدام اسم المستخدم من السوشيال ميديا
        $baseUsername = $socialUser->getNickname() ?? 
                       explode('@', $socialUser->getEmail())[0] ?? 
                       $provider . '_' . Str::random(5);

        $username = Str::slug($baseUsername);
        $counter = 1;

        // تحقق من أن اسم المستخدم فريد
        while (Programmer::where('user_name', $username)->exists()) {
            $username = Str::slug($baseUsername) . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * التحقق من مزود الخدمة (Provider) الصحيح
     */
    private function validateProvider($provider)
    {
        $allowedProviders = ['google', 'facebook', 'github'];
        
        if (!in_array($provider, $allowedProviders)) {
            throw new \Exception("Provider '{$provider}' is not supported");
        }
    }

    /**
     * الحصول على بيانات المستخدم الحالي (للتحقق من حالة التسجيل)
     */
    public function getAuthUser(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $programmer = $user->programmer;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $programmer->avatar_url ?? null,
                'profile_completed' => $programmer->profile_completed ?? false,
                'user_name' => $programmer->user_name ?? null,
                'phone' => $programmer->phone ?? null,
                'track' => $programmer->track ?? null,
            ]
        ]);
    }
}

