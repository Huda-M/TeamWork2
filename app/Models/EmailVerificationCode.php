<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

class EmailVerificationCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createForEmail(string $email): self
    {
        self::where('email', $email)->delete();

        $code = self::generateCode();

        $verificationCode = self::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new VerificationCodeMail($code));

        return $verificationCode;
    }

    public static function verify(string $email, string $code): bool
    {
        $verificationCode = self::where('email', $email)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if ($verificationCode) {
            $verificationCode->delete();
            return true;
        }

        return false;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
