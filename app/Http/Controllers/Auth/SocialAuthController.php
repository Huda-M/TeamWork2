<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAuth;
use App\Models\Programmer;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    /**
     * ✅ WEB: إعادة التوجيه إلى Google (للمتصفح فقط)
     */
    public function redirectToProvider(Request $request, $provider)
    {
        try {
            $this->validateProvider($provider);
            
            // بناء URL للـ Google OAuth
            $url = Socialite::driver($provider)
                ->stateless()
                ->redirect()
                ->getTargetUrl();
            
            // لو Mobile بيطلب (JSON request)
            if ($request->expectsJson() || $request->header('X-Platform') === 'mobile') {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $url,
                    'message' => 'Open this URL in browser'
                ]);
            }
            
            // Web: redirect عادي
            return redirect($url);
            
        } catch (\Exception $e) {
            Log::error("Social redirect error: " . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate auth URL'
                ], 500);
            }
            
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/login?error=oauth_failed');
        }
    }

    /**
     * ✅ WEB: معالجة الـ Callback من Google
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            $this->validateProvider($provider);
            
            // جلب بيانات المستخدم من Google
            $socialUser = Socialite::driver($provider)->stateless()->user();
            
            $result = $this->processSocialUser($socialUser, $provider);
            
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            
            // ✅ Redirect للـ Frontend مع token
            $redirectUrl = sprintf(
                '%s/auth/callback?token=%s&user=%s&role=%s&profile_completed=%s',
                $frontendUrl,
                $result['token'],
                urlencode(json_encode($result['user_data'])),
                $result['role'],
                $result['profile_completed'] ? '1' : '0'
            );
            
            return redirect($redirectUrl);
            
        } catch (\Exception $e) {
            Log::error("Social callback error: " . $e->getMessage());
            
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect($frontendUrl . '/login?error=auth_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * ✅ MOBILE: استقبال Google ID Token من Flutter
     */
    public function handleMobileToken(Request $request, $provider)
    {
        try {
            $this->validateProvider($provider);
            
            $request->validate([
                'token' => 'required|string', // Google ID Token
                'role' => 'nullable|in:programmer,company', // اختياري: تحديد الدور
            ]);
            
            // ✅ التحقق من الـ token مع Google
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->userFromToken($request->token);
            
            if (!$socialUser || !$socialUser->getEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }
            
            $result = $this->processSocialUser($socialUser, $provider, $request->role);
            
            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'user' => $result['user_data'],
                    'role' => $result['role'],
                    'profile_completed' => $result['profile_completed'],
                    'needs_profile_completion' => !$result['profile_completed'],
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error("Mobile token error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔧 CORE: معالجة بيانات المستخدم (مشترك بين Web و Mobile)
     */
    private function processSocialUser($socialUser, $provider, $requestedRole = null)
    {
        DB::beginTransaction();
        
        try {
            $email = $socialUser->getEmail();
            $name = $socialUser->getName() ?? $socialUser->getNickname() ?? 'User';
            $avatar = $socialUser->getAvatar();
            
            // البحث عن مستخدم موجود
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                // ✅ مستخدم جديد: تحديد الدور
                $role = $requestedRole ?? $this->getDefaultRole($provider);
                
                $user = User::create([
                    'full_name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]);
                
                // إنشاء البروفايل حسب الدور
                if ($role === 'programmer') {
                    $user->programmer()->create([
                        'user_name' => $this->generateUniqueUsername($socialUser, $provider),
                        'avatar_url' => $avatar,
                        'bio' => null,
                        'track' => null,
                        'profile_completed' => false,
                    ]);
                } elseif ($role === 'company') {
                    $user->company()->create([
                        'company_name' => $name,
                        'phone' => null,
                        'cr_number' => 'SOCIAL_' . Str::random(10),
                        'about' => null,
                        'country' => null,
                        'location' => null,
                        'industry' => null,
                        'size' => null,
                        'website' => null,
                        'logo' => $avatar,
                        'profile_completed' => false,
                    ]);
                }
                
            } else {
                // ✅ مستخدم موجود: تحديث الصورة لو فاضية
                if ($user->role === 'programmer' && $user->programmer) {
                    if (!$user->programmer->avatar_url && $avatar) {
                        $user->programmer->update(['avatar_url' => $avatar]);
                    }
                } elseif ($user->role === 'company' && $user->company) {
                    if (!$user->company->logo && $avatar) {
                        $user->company->update(['logo' => $avatar]);
                    }
                }
            }
            
            // حفظ بيانات OAuth
            $this->storeOrUpdateSocialAuth($user, $socialUser, $provider);
            
            // إنشاء Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // تجهيز بيانات الـ response
            $profileCompleted = $user->role === 'programmer'
                ? ($user->programmer->profile_completed ?? false)
                : ($user->company->profile_completed ?? false);
            
            $userData = [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->role === 'programmer' 
                    ? ($user->programmer->avatar_url ?? null)
                    : ($user->company->logo ?? null),
                'profile_completed' => $profileCompleted,
            ];
            
            // بيانات إضافية حسب الدور
            if ($user->role === 'programmer' && $user->programmer) {
                $userData['user_name'] = $user->programmer->user_name;
                $userData['track'] = $user->programmer->track;
            } elseif ($user->role === 'company' && $user->company) {
                $userData['company_name'] = $user->company->company_name;
            }
            
            DB::commit();
            
            return [
                'token' => $token,
                'user_data' => $userData,
                'role' => $user->role,
                'profile_completed' => $profileCompleted,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ✅ إكمال البروفايل (للـ Programmer بعد Social Login)
     */
    public function completeSocialRegistration(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            if ($user->role === 'programmer') {
                return $this->completeProgrammerProfile($request, $user);
            } elseif ($user->role === 'company') {
                return $this->completeCompanyProfile($request, $user);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid user role'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Complete profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete profile'
            ], 500);
        }
    }

    /**
     * إكمال بروفايل المبرمج
     */
    private function completeProgrammerProfile(Request $request, User $user)
    {
        $programmer = $user->programmer;
        
        if (!$programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found'
            ], 404);
        }
        
        if ($programmer->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already completed'
            ], 400);
        }
        
        $validated = $request->validate([
            'user_name' => 'required|string|max:255|unique:programmers,user_name,' . $programmer->id,
            'phone' => 'required|string|max:20',
            'track' => 'required|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'github_username' => 'nullable|string|max:255',
        ]);
        
        $programmer->update([
            ...$validated,
            'profile_completed' => true,
        ]);
        
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
                'profile_completed' => true,
            ]
        ]);
    }

    /**
     * إكمال بروفايل الشركة
     */
    private function completeCompanyProfile(Request $request, User $user)
    {
        $company = $user->company;
        
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }
        
        if ($company->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Profile already completed'
            ], 400);
        }
        
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cr_number' => 'required|string|max:50|unique:companies,cr_number,' . $company->id,
            'country' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'industry' => 'required|string|max:100',
            'size' => 'required|string|max:50',
            'website' => 'nullable|url|max:255',
            'about' => 'nullable|string|max:2000',
        ]);
        
        $company->update([
            ...$validated,
            'profile_completed' => true,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Company profile completed successfully',
            'data' => [
                'id' => $company->id,
                'company_name' => $company->company_name,
                'email' => $user->email,
                'phone' => $company->phone,
                'country' => $company->country,
                'profile_completed' => true,
            ]
        ]);
    }

    /**
     * الحصول على بيانات المستخدم المسجل
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
        
        $profileCompleted = false;
        $additionalData = [];
        
        if ($user->role === 'programmer' && $user->programmer) {
            $profileCompleted = $user->programmer->profile_completed;
            $additionalData = [
                'user_name' => $user->programmer->user_name,
                'avatar' => $user->programmer->avatar_url,
                'track' => $user->programmer->track,
                'bio' => $user->programmer->bio,
            ];
        } elseif ($user->role === 'company' && $user->company) {
            $profileCompleted = $user->company->profile_completed;
            $additionalData = [
                'company_name' => $user->company->company_name,
                'avatar' => $user->company->logo,
                'country' => $user->company->country,
                'industry' => $user->company->industry,
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_completed' => $profileCompleted,
                ...$additionalData,
            ]
        ]);
    }

    // ─── Helpers ───

    private function getDefaultRole($provider)
    {
        $roleMapping = [
            'github' => 'programmer',
            'google' => 'programmer', // Default for Google
            'facebook' => 'company',
        ];
        
        return $roleMapping[$provider] ?? 'programmer';
    }

    private function validateProvider($provider)
    {
        $allowed = ['google', 'facebook', 'github'];
        
        if (!in_array($provider, $allowed)) {
            throw new \Exception("Provider '{$provider}' is not supported");
        }
    }

    private function storeOrUpdateSocialAuth(User $user, $socialUser, $provider)
    {
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
        
        UserAuth::updateOrCreate(
            ['user_id' => $user->id, 'provider_type' => $provider],
            $authData
        );
    }

    private function generateUniqueUsername($socialUser, $provider)
    {
        $base = $socialUser->getNickname() 
            ?? explode('@', $socialUser->getEmail())[0] 
            ?? $provider . '_' . Str::random(5);
        
        $username = Str::slug($base);
        $counter = 1;
        
        while (Programmer::where('user_name', $username)->exists()) {
            $username = Str::slug($base) . '_' . $counter++;
        }
        
        return $username;
    }
}
