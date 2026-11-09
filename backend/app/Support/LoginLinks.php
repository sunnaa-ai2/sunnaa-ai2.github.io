<?php

namespace App\Support;

use App\Models\LoginToken;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Every e-mail has one permanent login link. It is created once and sent again on every request,
 * so any old e-mail the trainee kept still works. Whoever has the link can sign in to that account,
 * so it can be replaced (old one stops working) with:  php artisan sunnaa:login-link EMAIL --new
 */
class LoginLinks
{
    public static function token(string $email, bool $fresh = false): string
    {
        $email = strtolower(trim($email));
        if ($fresh) {
            self::forget($email);
        }

        $row = LoginToken::where('email', $email)->whereNull('expires_at')->whereNotNull('token_enc')->latest('id')->first();
        if ($row) {
            try {
                return Crypt::decryptString($row->token_enc);
            } catch (DecryptException) {
                $row->delete();   // APP_KEY changed: make a new link
            }
        }

        $plain = Str::random(48);
        LoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plain),
            'token_enc' => Crypt::encryptString($plain),
            'expires_at' => null,
        ]);

        return $plain;
    }

    public static function url(string $plain, ?string $invite = null): string
    {
        return rtrim(config('spark.frontend_url'), '/').'/?login='.$plain.($invite ? '&invite='.$invite : '');
    }

    /** Removes every link of an e-mail (account deleted, or link replaced). */
    public static function forget(string $email): void
    {
        LoginToken::where('email', strtolower(trim($email)))->delete();
    }
}
