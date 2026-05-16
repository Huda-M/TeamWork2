<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Auth\LoginRequest;
use App\Http\Requests\Company\Auth\RegisterRequest;
use App\Models\User;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {

        $validate = $request->validated();

        if (! $token = auth()->guard('')->attempt($validate)) {
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

        return response()->json([
            'message' => 'Login successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'message' => 'Logout successfully',
            'status' => 200,
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create($request->validated());
        $token = auth()->attempt($request->validated());

        return response()->json([
            'message' => 'Register successfully',
            'status' => 200,
            'user' => $user,
            'token' => $token,
        ]);
    }
}
