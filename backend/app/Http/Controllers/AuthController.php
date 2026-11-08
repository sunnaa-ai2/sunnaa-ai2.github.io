<?php

namespace App\Http\Controllers;

use App\Mail\LoginLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Sends a one-time login link. Always answers "ok" so nobody can probe which e-mails exist. */
    public function link(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'invite' => ['nullable', 'uuid'],
        ]);
        $email = strtolower(trim($data['email']));
        $plain = Str::random(48);
        $ttl = config('spark.login_ttl');

        LoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $url = rtrim(config('spark.frontend_url'), '/').'/?login='.$plain.(! empty($data['invite']) ? '&invite='.$data['invite'] : '');
        Mail::to($email)->send(new LoginLinkMail($url, $ttl));

        return response()->json(['ok' => true]);
    }

    /** Exchanges the token from the e-mail for an API token (the site stores it and sends it as a Bearer header). */
    public function verify(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);

        $user = DB::transaction(function () use ($data) {
            $row = LoginToken::where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_if(! $row || $row->used_at || $row->expires_at->isPast(), 422, 'LOGIN_LINK_INVALID');
            $row->update(['used_at' => now()]);

            $user = User::firstOrCreate(['email' => $row->email]);
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });

        $token = $user->createToken('web', ['*'], now()->addDays(config('spark.token_days')))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'email' => $user->email],
            'is_admin' => $user->isAdmin(),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => ['id' => $user->id, 'email' => $user->email],
            'is_admin' => $user->isAdmin(),
            'is_member' => $user->isMember(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
