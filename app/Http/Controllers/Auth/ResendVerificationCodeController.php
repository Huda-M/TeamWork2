<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResendVerificationCodeController extends Controller
{
    public function resend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User is not found.'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'The account is already activated.'], 400);
        }

        $user->generateVerificationCode();

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'The verification code has been sent again.'], 200);
    }
}
