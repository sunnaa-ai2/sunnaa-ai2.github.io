<?php

namespace App\Http\Controllers;

use App\Mail\LoginLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use App\Support\LoginLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /** Sends the e-mail's permanent login link. Always answers "ok" so nobody can probe which e-mails exist. */
    public function link(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'invite' => ['nullable', 'uuid'],
        ]);
        $email = strtolower(trim($data['email']));
        $url = LoginLinks::url(LoginLinks::token($email), $data['invite'] ?? null);
        Mail::to($email)->send(new LoginLinkMail($url));

        return response()->json(['ok' => true]);
    }

    /** Exchanges the token from the e-mail for an API token (the site stores it and sends it as a Bearer header). */
    public function verify(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);

        $user = DB::transaction(function () use ($data) {
            $row = LoginToken::where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            // permanent links (no expires_at) work every time; old 30-minute links work once
            abort_if(! $row || ($row->expires_at && ($row->used_at || $row->expires_at->isPast())), 422, 'LOGIN_LINK_INVALID');
            $row->update(['used_at' => now()]);   // last use

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
