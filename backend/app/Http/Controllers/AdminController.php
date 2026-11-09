<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Invite;
use App\Models\Profile;
use App\Support\Files;
use App\Support\LoginLinks;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function invites()
    {
        return response()->json(Invite::latest()->get()->map(fn ($i) => Present::invite($i)));
    }

    public function createInvite(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $invite = Invite::create(['email' => strtolower(trim($data['email'])), 'created_by' => $request->user()->id]);

        return response()->json(Present::invite($invite->fresh()), 201);
    }

    public function deleteInvite(Invite $invite)
    {
        $invite->delete();

        return response()->json(['ok' => true]);
    }

    /** Removes a member completely; they can only come back with a new invite. */
    public function deleteMember(Request $request, Profile $profile)
    {
        abort_if($profile->user_id === $request->user()->id, 422, 'CANNOT_DELETE_SELF');
        Files::purgeMember($profile);
        DB::transaction(function () use ($profile) {
            $user = $profile->user;
            $user->tokens()->delete();
            LoginLinks::forget($user->email);
            $user->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function saveExercise(Request $request, int $no)
    {
        abort_unless($no >= 1 && $no <= 999, 422, 'EXERCISE_NUMBER');
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
        $exercise = Exercise::updateOrCreate(['no' => $no], ['title' => $data['title'] ?? null, 'description' => $data['description'] ?? null]);

        return response()->json(Present::exercise($exercise));
    }
}
