<?php

use App\Models\LoginToken;
use App\Models\Profile;
use App\Models\User;
use App\Support\LoginLinks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Old login links are useless after they expire; keep the table small.
Schedule::call(fn () => LoginToken::where('expires_at', '<', now()->subDay())->delete())->daily();

/*
 * Prints the permanent login link of an e-mail, without sending an e-mail:
 *   php artisan sunnaa:login-link you@gmail.com
 * --new replaces the link (the old one stops working), e.g. if it was shared by mistake.
 */
Artisan::command('sunnaa:login-link {email} {--new : Replace the link; the old one stops working}', function (string $email) {
    $this->info(LoginLinks::url(LoginLinks::token($email, (bool) $this->option('new'))));
    $this->line('Permanent link: it signs in to this account every time. Do not share it.');
})->purpose('Print the permanent login link of an e-mail (no mail is sent)');

/*
 * One-time import of the old Supabase data (users + profiles), from a JSON file or stdin:
 *   echo '{"users":[...],"profiles":[...]}' | php artisan sunnaa:import-supabase
 * users:    [{"id": old uuid, "email", "created_at"}]
 * profiles: [{"id": old user uuid, "full_name", "bio", "created_at"}]
 * Safe to run twice: users are matched by e-mail, and an existing profile only gets its empty fields filled.
 */
Artisan::command('sunnaa:import-supabase {file? : JSON file (stdin when omitted)}', function (?string $file = null) {
    $data = json_decode((string) ($file ? @file_get_contents($file) : stream_get_contents(STDIN)), true);
    if (! is_array($data)) {
        $this->error('Invalid JSON.');

        return 1;
    }

    DB::transaction(function () use ($data) {
        $ids = [];   // old Supabase id => MySQL user id
        foreach ($data['users'] ?? [] as $u) {
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->line("user exists:      {$email}");
            } else {
                $user = new User;
                $user->forceFill([
                    'id' => ! empty($u['id']) && ! User::whereKey($u['id'])->exists() ? $u['id'] : null,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'created_at' => $u['created_at'] ?? now(),
                ])->save();
                $this->info("user created:     {$email}");
            }
            $ids[$u['id'] ?? $email] = $user->id;
        }

        foreach ($data['profiles'] ?? [] as $p) {
            $uid = $ids[$p['id'] ?? ''] ?? null;
            $name = mb_substr(trim((string) ($p['full_name'] ?? '')), 0, 60);
            $bio = mb_substr(trim((string) ($p['bio'] ?? '')), 0, 300) ?: null;
            if (! $uid || $name === '') {
                $this->warn('profile skipped:  no matching user or empty name');
                continue;
            }
            $profile = Profile::find($uid);
            if (! $profile) {
                (new Profile)->forceFill([
                    'user_id' => $uid,
                    'full_name' => $name,
                    'bio' => $bio,
                    'created_at' => $p['created_at'] ?? now(),
                ])->save();
                $this->info("profile created:  {$name}");
            } else {
                if (! $profile->bio && $bio) {
                    $profile->update(['bio' => $bio]);
                }
                $this->line("profile exists:   {$profile->full_name} (kept, empty fields filled)");
            }
        }
    });

    $this->info('Import finished.');

    return 0;
})->purpose('Import users and profiles exported from the old Supabase project');
