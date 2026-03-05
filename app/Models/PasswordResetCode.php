<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetCodeMail;
use Illuminate\Support\Str;

class PasswordResetCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'verified_at'];
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime'
    ];

    public static function createForEmail(string $email): self
    {
        self::where('email', $email)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $resetCode = self::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new PasswordResetCodeMail($code));

        return $resetCode;
    }

    public static function verify(string $email, string $code): ?self
    {
        $resetCode = self::where('email', $email)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if ($resetCode) {
            $resetCode->update(['verified_at' => now()]);
            return $resetCode;
        }

        return null;
    }


    public static function isVerified(string $email): bool
    {
        return self::where('email', $email)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(15))
            ->exists();
    }


    public static function deleteCode(string $email): void
    {
        self::where('email', $email)->delete();
    }
}
