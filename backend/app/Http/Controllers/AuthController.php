<?php

namespace App\Http\Controllers;

use App\Models\LoginToken;
use App\Models\User;
use App\Support\Notices;
use App\Support\PasswordResets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Accounts: register (waits for an admin), sign in with username or e-mail + password,
 * reset a forgotten password by e-mail, change password / username.
 */
class AuthController extends Controller
{
    private const USERNAME = ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_.]+$/'];

    private const MESSAGES = [
        'full_name.required' => 'NAME_REQUIRED', 'full_name.min' => 'NAME_REQUIRED',
        'username.required' => 'USERNAME_INVALID', 'username.min' => 'USERNAME_INVALID', 'username.max' => 'USERNAME_INVALID',
        'username.regex' => 'USERNAME_INVALID', 'username.unique' => 'USERNAME_TAKEN',
        'email.required' => 'EMAIL_INVALID', 'email.email' => 'EMAIL_INVALID', 'email.unique' => 'EMAIL_TAKEN',
        'password.required' => 'PASSWORD_SHORT', 'password.min' => 'PASSWORD_SHORT',
    ];

    private function lower(Request $request, string ...$keys): void
    {
        foreach ($keys as $k) {
            if ($request->has($k)) {
                $request->merge([$k => strtolower(trim((string) $request->input($k)))]);
            }
        }
    }

    private static function row(User $u): array
    {
        return ['id' => $u->id, 'email' => $u->email, 'username' => $u->username];
    }

    private function session(User $user): array
    {
        $token = $user->createToken('web', ['*'], now()->addDays(config('spark.token_days')))->plainTextToken;

        return ['token' => $token, 'user' => self::row($user), 'is_admin' => $user->isAdmin()];
    }

    /** A new trainee asks to join; the account stays "pending" until an admin approves it. */
    public function register(Request $request)
    {
        $this->lower($request, 'username', 'email');
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:60'],
            'username' => [...self::USERNAME, 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], self::MESSAGES);
        // admin accounts are opened with a reset link to the admin's own inbox, never by this form
        abort_if(in_array($data['email'], config('spark.admins'), true), 422, 'EMAIL_RESERVED');

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => 'pending',
            'requested_name' => trim($data['full_name']),
        ]);
        Notices::joinRequest($user);

        return response()->json(['ok' => true, 'status' => 'pending'], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'max:100']]);
        $login = strtolower(trim($data['login']));
        $user = User::where(str_contains($login, '@') ? 'email' : 'username', $login)->first();

        abort_unless($user && $user->password && Hash::check($data['password'], $user->password), 422, 'LOGIN_FAILED');
        abort_unless($user->status === 'approved', 403, 'PENDING_APPROVAL');

        return response()->json($this->session($user));
    }

    /** Always answers "ok", so nobody can probe which e-mails have accounts. */
    public function forgot(Request $request)
    {
        $this->lower($request, 'email');
        $email = $request->validate(['email' => ['required', 'email', 'max:190']], self::MESSAGES)['email'];
        $user = User::where('email', $email)->first();
        // approved accounts, and admins opening their account for the first time
        $allowed = $user ? $user->status === 'approved' : in_array($email, config('spark.admins'), true);

        if ($allowed) {
            Notices::passwordReset($email, PasswordResets::url(PasswordResets::create($email)), $user?->username);
        }

        return response()->json(['ok' => true]);
    }

    /** Sets a new password from an e-mailed link, signs out other devices and signs in here. */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:48'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], self::MESSAGES);

        $user = DB::transaction(function () use ($data) {
            $row = LoginToken::where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_if(! $row || ! $row->expires_at || $row->used_at || $row->expires_at->isPast(), 422, 'RESET_LINK_INVALID');

            $user = User::where('email', $row->email)->first();
            if (! $user) {
                abort_unless(in_array($row->email, config('spark.admins'), true), 422, 'RESET_LINK_INVALID');
                $user = User::create(['email' => $row->email, 'status' => 'approved']);
            }
            abort_unless(($user->status ?? 'approved') === 'approved', 403, 'PENDING_APPROVAL');

            $user->forceFill(['password' => $data['password'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->tokens()->delete();
            PasswordResets::forget($row->email);

            return $user;
        });

        return response()->json($this->session($user));
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => self::row($user),
            'is_admin' => $user->isAdmin(),
            'is_member' => $user->isMember(),
            'pending_requests' => $user->isAdmin() ? User::where('status', 'pending')->count() : 0,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /** Needs the current password (when there is one); other devices are signed out. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], self::MESSAGES);
        abort_if($user->password && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password), 422, 'WRONG_PASSWORD');

        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['ok' => true]);
    }

    public function changeUsername(Request $request)
    {
        $user = $request->user();
        $this->lower($request, 'username');
        $data = $request->validate(['username' => [...self::USERNAME, Rule::unique('users', 'username')->ignore($user->id)]], self::MESSAGES);
        $user->forceFill(['username' => $data['username']])->save();

        return response()->json(['ok' => true, 'username' => $user->username]);
    }
}
