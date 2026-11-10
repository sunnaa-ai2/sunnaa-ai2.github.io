<?php

namespace App\Http\Controllers;

use App\Models\AiReview;
use App\Models\Work;
use App\Support\Claude;
use App\Support\Present;
use App\Support\Prompts;
use Illuminate\Http\Request;

/** Claude's instant feedback on a submission, for its owner (and admins). */
class ReviewController extends Controller
{
    private function canSee(Request $request, Work $work): void
    {
        $user = $request->user();
        abort_unless($work->user_id === $user->id || $user->isAdmin(), 403, 'FORBIDDEN');
    }

    public function index(Request $request, Work $work)
    {
        $this->canSee($request, $work);

        return response()->json(AiReview::where('work_id', $work->id)->latest()->get()->map(fn ($r) => Present::review($r)));
    }

    public function store(Request $request, Work $work)
    {
        $this->canSee($request, $work);
        abort_unless(Claude::enabled(), 503, 'AI_DISABLED');

        $user = $request->user();
        if (! $user->isAdmin()) {
            $today = AiReview::where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
            abort_if($today >= config('spark.ai.reviews_per_day'), 429, 'AI_DAILY_LIMIT');
        }

        $body = Claude::ask(Prompts::reviewSystem(), [['role' => 'user', 'content' => Prompts::reviewContent($work->load('files'))]], 1500);
        abort_if($body === '', 502, 'AI_UNAVAILABLE');

        $review = AiReview::create(['work_id' => $work->id, 'user_id' => $user->id, 'body' => $body, 'model' => Claude::model()]);

        return response()->json(Present::review($review), 201);
    }
}
