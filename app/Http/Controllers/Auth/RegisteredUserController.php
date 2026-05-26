<?php
// app/Http/Controllers/Auth/RegisteredUserController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    /**
     * الخطوة 1: إرسال كود التفعيل
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'source' => ['required', 'in:mobile,web'],
        ]);

        $role = $request->source === 'mobile' ? 'programmer' : 'company';

        cache()->put('registration:' . $request->email, [
            'full_name' => $request->full_name,
            'password' => $request->password,
            'role' => $role,
            'source' => $request->source,
        ], now()->addMinutes(30));

        EmailVerificationCode::createForEmail($request->email);

        return response()->json([
            'message' => 'Verification code sent to your email',
            'email' => $request->email,
            'source' => $request->source,
            'role' => $role,
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

    DB::beginTransaction();
    try {
        // إنشاء المستخدم
        $user = User::create([
            'full_name' => $registrationData['full_name'],
            'email' => $request->email,
            'password' => Hash::make($registrationData['password']),
            'role' => $registrationData['role'],
            'email_verified_at' => now(),
        ]);

        // 🔹 إنشاء البروفايل حسب الدور
        if ($user->role === 'programmer') {
            $user->programmer()->create([
                'profile_completed' => false,
            ]);
        }  elseif ($user->role === 'company') {
    $user->company()->create([
        'company_name' => $user->full_name,
        'phone' => '0000000000',                         // قيمة مؤقتة
        'cr_number' => 'TEMP_' . Str::random(10),        // قيمة مؤقتة فريدة
        'about' => null,
        'country' => 'Unknown',                          // قيمة مؤقتة
        'location' => 'Unknown',                         // قيمة مؤقتة
        'industry' => 'General',                         // قيمة افتراضية لـ NOT NULL
        'size' => '1-10',                                // قيمة افتراضية
        'website' => 'https://temp.com',                 // قيمة افتراضية
        'subscription_end_date' => now()->addYear(),     // تاريخ مستقبلي
        'profile_completed' => false,
    ]);

        }

        event(new Registered($user));

        cache()->forget('registration:' . $request->email);

        Auth::login($user);

        DB::commit();

        $responseData = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at,
        ];

        if ($user->role === 'programmer' && $user->programmer) {
            $responseData['programmer'] = $user->programmer;
        }
        if ($user->role === 'company' && $user->company) {
            $responseData['company'] = $user->company;
        }

        return response()->json([
            'message' => 'Registration successful',
            'user' => $responseData,
            'token' => $user->createToken('auth_token')->plainTextToken,
            'profile_completed' => false,
            'source' => $registrationData['source'],
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Registration failed', [
            'email' => $request->email,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Registration failed. Please try again.',
        ], 500);
    }
}

    /**
     * إعادة إرسال الكود
     */
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


public function completeProfile(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user || $user->role !== 'programmer') {
            return response()->json(['message' => 'Only programmers can complete profile'], 403);
        }
        $programmer = $user->programmer;
        if (!$programmer) {
            return response()->json(['message' => 'Programmer profile not found'], 404);
        }

        $validated = $request->validate([
            'experience_level' => 'required|in:beginner,junior,intermediate,senior,advanced,expert',
            'track' => 'required|string',
        ]);

        $pointsMap = [
    'beginner'    => 0,
    'junior'      => 50,
    'intermediate'=> 100,
    'senior'      => 200,
    'advanced'    => 350,
    'expert'      => 500,
];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_url'] = $path;  // تم التصحيح
        }

        $validated['total_score'] = $pointsMap[$validated['experience_level']] ?? 0;
        $validated['profile_completed'] = true;

        $programmer->update($validated);

        return response()->json(['success' => true, 'message' => 'Profile completed']);
    } catch (\Exception $e) {
        Log::error('Profile completion error: ' . $e->getMessage());
        return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500); // لتسهيل التصحيح
    }
}
   

public function completeCompanyProfile(Request $request): JsonResponse
{
    $user = $request->user();

    if ($user->role !== 'company') {
        return response()->json([
            'success' => false,
            'message' => 'Only companies can complete profile here'
        ], 403);
    }

    $company = $user->company;

    if (!$company) {
        return response()->json([
            'success' => false,
            'message' => 'Company profile not found'
        ], 404);
    }

    $validated = $request->validate([
        'company_name' => ['required', 'string', 'max:255'],
        'phone'        => ['required', 'string', 'max:20'],
        'cr_number'    => ['required', 'string', 'unique:companies,cr_number,' . $company->id],
        'about'        => ['required', 'string', 'min:20'],
        'country'      => ['required', 'string', 'max:100'],
        'location'     => ['required', 'string', 'max:255'],
        'logo'         => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // 5MB
        'social_links' => ['nullable', 'array'],
        'social_links.*' => ['url', 'max:255'], // كل رابط يجب أن يكون URL صالح
    ]);

    // رفع الشعار
    if ($request->hasFile('logo')) {
        // حذف الشعار القديم إن وجد
        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }
        $path = $request->file('logo')->store('company_logos', 'public');
        $validated['logo'] = $path;
    }

    // تحديث الحقول
    $company->update([
        'company_name' => $validated['company_name'],
        'phone'        => $validated['phone'],
        'cr_number'    => $validated['cr_number'],
        'about'        => $validated['about'],
        'country'      => $validated['country'],
        'location'     => $validated['location'],
        'logo'         => $validated['logo'] ?? $company->logo,
        'social_links' => $validated['social_links'] ?? null,
        'profile_completed' => true,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Company profile completed successfully',
        'data'    => $company->fresh()
    ], 200);
}
    
    public function profileStatus(Request $request): JsonResponse
{
    $user = $request->user();

    if ($user->role === 'programmer') {
        $profile = $user->programmer;
        $completed = $profile ? $profile->profile_completed : false;
    } elseif ($user->role === 'company') {
        $profile = $user->company;
        $completed = $profile ? $profile->profile_completed : false;
    } else {
        $completed = true;
        $profile = null;
    }

    return response()->json([
        'profile_completed' => $completed,
        'role' => $user->role,
        'user' => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'profile' => $profile,
    ], 200);
}
}
