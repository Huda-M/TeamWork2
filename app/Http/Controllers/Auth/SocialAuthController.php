<?php
// app/Http/Controllers/Auth/SocialAuthController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
{
    try {
        $socialUser = Socialite::driver($provider)->user();
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    // البحث أو إنشاء المستخدم
    $user = User::where('email', $socialUser->getEmail())->first();

    if (!$user) {
        $user = User::create([
            'full_name'          => $socialUser->getName() ?? $socialUser->getNickname(),
            'email'              => $socialUser->getEmail(),
            'password'           => Hash::make(Str::random(24)),
            'role'               => 'programmer',
            'email_verified_at'  => now(),
        ]);
        $user->programmer()->create([]);
    }

    Auth::login($user);
    $token = $user->createToken('auth_token')->plainTextToken;

    // 🔁 إعادة التوجيه إلى واجهتك الأمامية (Frontend)
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
    return redirect($frontendUrl . '/auth/callback?token=' . $token . '&user=' . urlencode(json_encode([
        'id' => $user->id,
        'name' => $user->full_name,
        'email' => $user->email,
        'role' => $user->role,
    ])));
}

    public function completeSocialRegistration(Request $request)
    {
        // إذا أردت إكمال بيانات إضافية بعد التسجيل عبر السوشيال ميديا
        $user = $request->user();
        // ... المنطق المطلوب
        return response()->json($user);
    }
}
