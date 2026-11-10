<?php

namespace App\Support;

use App\Models\LoginToken;
use Illuminate\Support\Str;

/** One-time password-reset links, kept hashed in login_tokens. */
class PasswordResets
{
    public static function create(string $email): string
    {
        $email = strtolower(trim($email));
        LoginToken::where('email', $email)->delete();   // only the newest link works
        $plain = Str::random(48);
        LoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(config('spark.reset_ttl')),
        ]);

        return $plain;
    }

    public static function url(string $plain): string
    {
        return rtrim(config('spark.frontend_url'), '/').'/?reset='.$plain;
    }

    public static function forget(string $email): void
    {
        LoginToken::where('email', strtolower(trim($email)))->delete();
    }
}
