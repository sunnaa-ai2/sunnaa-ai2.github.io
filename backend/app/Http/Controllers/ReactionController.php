<?php

namespace App\Http\Controllers;

use App\Models\Reaction;
use App\Models\Work;
use App\Support\Notify;
use App\Support\Present;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    /**
     * Likes are public. A dislike is visible only to the person who gave it,
     * the owner of the work, and admins.
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $query = Reaction::query();

        if (! $user?->isAdmin()) {
            $uid = $user?->id;
            $query->where(function ($q) use ($uid) {
                $q->where('value', 1);
                if ($uid) {
                    $q->orWhere('user_id', $uid)
                        ->orWhereIn('work_id', Work::select('id')->where('user_id', $uid));
                }
            });
        }

        return response()->json($query->get()->map(fn ($r) => Present::reaction($r)));
    }

    /** value: 1 like, -1 dislike, 0 remove. */
    public function upsert(Request $request, Work $work)
    {
        $data = $request->validate(['value' => ['required', 'integer', 'in:-1,0,1']]);
        $uid = $request->user()->id;
        $current = Reaction::where(['work_id' => $work->id, 'user_id' => $uid])->first();

        if ((int) $data['value'] === 0) {
            $current?->delete();

            return response()->json(['ok' => true]);
        }

        $wasLike = $current?->value === 1;
        $reaction = Reaction::updateOrCreate(['work_id' => $work->id, 'user_id' => $uid], ['value' => (int) $data['value']]);
        if ($reaction->value === 1 && ! $wasLike) {
            Notify::about($work, $uid, 'like');
        }

        return response()->json(Present::reaction($reaction));
    }
}
