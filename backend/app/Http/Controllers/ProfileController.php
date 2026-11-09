<?php

namespace App\Http\Controllers;

use App\Support\Files;
use App\Support\LoginLinks;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:60'],
            'bio' => ['nullable', 'string', 'max:300'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);
        $profile = $request->user()->profile;
        $profile->full_name = trim($data['full_name']);
        $profile->bio = isset($data['bio']) ? trim($data['bio']) : null;

        if ($request->hasFile('avatar')) {
            $old = $profile->avatar_path;
            $profile->avatar_path = Files::storeAvatar($profile->user_id, $request->file('avatar'));
            Files::delete($old);
        }
        $profile->save();

        return response()->json(Present::profile($profile));
    }

    /** Deletes the account and everything it owns (works, files, comments, reactions, notifications). */
    public function destroy(Request $request)
    {
        $user = $request->user();
        if ($profile = $user->profile) {
            Files::purgeMember($profile);
        }
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            LoginLinks::forget($user->email);
            $user->delete();
        });

        return response()->json(['ok' => true]);
    }
}
