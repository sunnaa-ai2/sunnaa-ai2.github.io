<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Profile;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JoinController extends Controller
{
    /**
     * Becoming a member: an invite link works once, only for the e-mail it was sent to.
     * Admins may create their own profile without an invite.
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'token' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);
        $user = $request->user();

        if ($existing = $user->profile) {
            return response()->json(Present::profile($existing));
        }

        $profile = DB::transaction(function () use ($data, $user) {
            if (empty($data['token'])) {
                abort_unless($user->isAdmin(), 422, 'INVITE_REQUIRED');
            } else {
                $invite = Invite::whereKey($data['token'])->lockForUpdate()->first();
                abort_unless($invite, 422, 'INVITE_INVALID');
                abort_if($invite->used_at, 422, 'INVITE_USED');
                abort_unless(strtolower($invite->email) === strtolower($user->email), 422, 'INVITE_EMAIL_MISMATCH');
                $invite->update(['used_at' => now(), 'used_by' => $user->id]);
            }

            return Profile::create(['user_id' => $user->id, 'full_name' => trim($data['name'])]);
        });

        return response()->json(Present::profile($profile), 201);
    }
}
