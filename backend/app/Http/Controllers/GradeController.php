<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Grade;
use App\Models\Profile;
use App\Models\Work;
use App\Support\Present;
use Illuminate\Http\Request;

/** Private marks: admins give them, only the work's owner (and admins) can read them. */
class GradeController extends Controller
{
    /** Admins get every grade; anyone else only the grades of their own works. */
    public function index(Request $request)
    {
        $user = $request->user();
        $q = Grade::query();
        if (! $user->isAdmin()) {
            $q->whereIn('work_id', Work::where('user_id', $user->id)->select('id'));
        }

        return response()->json($q->get()->map(fn ($g) => Present::grade($g)));
    }

    public function upsert(Request $request, Work $work)
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['score.*' => 'GRADE_INVALID']);

        $grade = Grade::find($work->id);
        $changed = ! $grade || $grade->score !== (int) $data['score'] || $grade->comment !== ($data['comment'] ?? null);
        $grade = Grade::updateOrCreate(['work_id' => $work->id], [
            'score' => (int) $data['score'],
            'comment' => isset($data['comment']) ? trim($data['comment']) : null,
            'graded_by' => $request->user()->id,
        ]);

        if ($changed && $work->user_id !== $request->user()->id) {
            $actor = Profile::find($request->user()->id) ? $request->user()->id : null;
            AppNotification::where(['user_id' => $work->user_id, 'work_id' => $work->id, 'kind' => 'grade'])->delete();
            AppNotification::create(['user_id' => $work->user_id, 'actor_id' => $actor, 'kind' => 'grade', 'work_id' => $work->id]);
        }

        return response()->json(Present::grade($grade));
    }

    public function destroy(Work $work)
    {
        Grade::where('work_id', $work->id)->delete();
        AppNotification::where(['work_id' => $work->id, 'kind' => 'grade'])->delete();

        return response()->json(['ok' => true]);
    }
}
