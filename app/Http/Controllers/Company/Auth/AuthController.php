<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Auth\ChangePasswordRequest;
use App\Http\Requests\Company\Auth\LoginRequest;
use App\Http\Requests\Company\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {

        $validate = $request->validated();

        if (! Auth::attempt($validate)) {
            return response()->json([
                'message' => 'Email or password is invalid',
                'status' => 401,
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->role !== 'company') {
            return response()->json([
                'message' => 'You are not authorized to login as company',
                'status' => 403,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout()
    {

        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successfully',
            'status' => 200,
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['role'] = 'company';
        $user = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Register successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $data = $request->validated();

        $user = auth()->user();
        if (! Hash::check($data['old_password'], $user->password)) {
            return response()->json([
                'message' => 'Old password is incorrect',
                'status' => 401,
            ]);
        }
        $user->update($data);

        return response()->json([
            'message' => 'Password changed successfully',
            'status' => 200,
            'user' => $user->load('company'),
        ]);
    }
}
