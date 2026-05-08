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

    /**
     * الخطوة 2: التحقق من الكود وإنشاء الحساب
     */
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

            event(new Registered($user));

            cache()->forget('registration:' . $request->email);

            Auth::login($user);

            DB::commit();

            // تحميل البيانات الإضافية
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
                'error' => $e->getMessage()
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
    $user = Auth::user();
    $programmer = $user->programmer;
    
    $validated = $request->validate([
        'user_name' => 'required|string|unique:programmers,user_name,'.$programmer->id,
        'phone' => 'nullable|string',
        'experience_level' => 'required|in:beginner,junior,senior,expert',
        'track' => 'required|string',
        'bio' => 'nullable|string',
        'avatar' => 'nullable|image|max:2048',
    ]);
    
    // تعيين النقاط (total_score) حسب المستوى
    $pointsMap = [
        'beginner' => 0,
        'junior' => 50,
        'senior' => 200,
    ];
    
    if ($request->hasFile('avatar')) {
        $path = $request->file('avatar')->store('avatars', 'public');
        $validated['avatar'] = $path;
    }
    
    $validated['total_score'] = $pointsMap[$validated['experience_level']];
    $validated['profile_completed'] = true;
    
    $programmer->update($validated);
    
    return response()->json(['success' => true, 'message' => 'Profile completed']);
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
