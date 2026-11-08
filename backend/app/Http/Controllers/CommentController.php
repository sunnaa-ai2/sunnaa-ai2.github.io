<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Work;
use App\Support\Notify;
use App\Support\Present;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** Light list used for counters: which work, which author. */
    public function rows()
    {
        return response()->json(Comment::select('work_id', 'user_id')->get()->map(fn ($c) => ['work_id' => $c->work_id, 'user_id' => $c->user_id]));
    }

    public function index(Work $work)
    {
        return response()->json(Comment::where('work_id', $work->id)->orderBy('created_at')->get()->map(fn ($c) => Present::comment($c)));
    }

    public function store(Request $request, Work $work)
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:1000']]);
        $comment = Comment::create(['work_id' => $work->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        Notify::about($work, $request->user()->id, 'comment');

        return response()->json(Present::comment($comment), 201);
    }

    public function destroy(Request $request, Comment $comment)
    {
        $user = $request->user();
        abort_unless($comment->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
        $comment->delete();

        return response()->json(['ok' => true]);
    }
}
