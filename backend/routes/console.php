<?php

use App\Models\LoginToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

// Old login links are useless after they expire; keep the table small.
Schedule::call(fn () => LoginToken::where('expires_at', '<', now()->subDay())->delete())->daily();

/*
 * Prints a one-time login link without sending an e-mail.
 * Useful on the server before SMTP is configured:  php artisan sunnaa:login-link you@gmail.com
 */
Artisan::command('sunnaa:login-link {email}', function (string $email) {
    $plain = Str::random(48);
    $ttl = config('spark.login_ttl');
    LoginToken::create([
        'email' => strtolower(trim($email)),
        'token_hash' => hash('sha256', $plain),
        'expires_at' => now()->addMinutes($ttl),
    ]);
    $this->info(rtrim(config('spark.frontend_url'), '/').'/?login='.$plain);
    $this->line("Valid once, for {$ttl} minutes.");
})->purpose('Print a one-time login link for an e-mail (no mail is sent)');
