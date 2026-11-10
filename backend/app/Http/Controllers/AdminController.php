<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Profile;
use App\Models\User;
use App\Support\Files;
use App\Support\Notices;
use App\Support\PasswordResets;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /** People who registered and wait for approval, oldest first. */
    public function requests()
    {
        return response()->json(User::where('status', 'pending')->orderBy('created_at')->get()->map(fn ($u) => Present::request($u)));
    }

    /** Approves a registration: the account becomes active and the trainee gets a public page. */
    public function approve(User $user)
    {
        abort_unless($user->status === 'pending', 422, 'REQUEST_NOT_PENDING');
        $profile = DB::transaction(function () use ($user) {
            $user->forceFill(['status' => 'approved'])->save();

            return Profile::firstOrCreate(['user_id' => $user->id], ['full_name' => $user->requested_name ?: $user->username]);
        });
        Notices::approved($user);

        return response()->json(Present::profile($profile));
    }

    /** Rejects a registration: the request is removed, so the person may apply again. */
    public function reject(User $user)
    {
        abort_unless($user->status === 'pending', 422, 'REQUEST_NOT_PENDING');
        Notices::rejected($user);
        $user->delete();

        return response()->json(['ok' => true]);
    }

    /** Removes a member completely; to come back they register again and wait for approval. */
    public function deleteMember(Request $request, Profile $profile)
    {
        abort_if($profile->user_id === $request->user()->id, 422, 'CANNOT_DELETE_SELF');
        Files::purgeMember($profile);
        DB::transaction(function () use ($profile) {
            $user = $profile->user;
            $user->tokens()->delete();
            PasswordResets::forget($user->email);
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
