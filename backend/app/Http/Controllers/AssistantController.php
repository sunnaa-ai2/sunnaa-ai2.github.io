<?php

namespace App\Http\Controllers;

use App\Models\CourseMaterial;
use App\Support\Claude;
use App\Support\Present;
use App\Support\Prompts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Course materials (admins write them) and the assistant that answers from them (members and admins). */
class AssistantController extends Controller
{
    private function canUse(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isMember() || $user->isAdmin(), 403, 'NOT_MEMBER');
    }

    public function materials(Request $request)
    {
        $this->canUse($request);

        return response()->json(CourseMaterial::orderBy('position')->orderBy('created_at')->get()->map(fn ($m) => Present::material($m)));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:60000'],
            'position' => ['nullable', 'integer', 'between:0,999'],
        ], ['title.*' => 'MATERIAL_TITLE', 'body.*' => 'MATERIAL_BODY']);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['position'] ??= (int) CourseMaterial::max('position') + 1;

        return response()->json(Present::material(CourseMaterial::create($data)), 201);
    }

    public function update(Request $request, CourseMaterial $material)
    {
        $material->update($this->validated($request));

        return response()->json(Present::material($material));
    }

    public function destroy(CourseMaterial $material)
    {
        $material->delete();

        return response()->json(['ok' => true]);
    }

    /** messages: the conversation so far, oldest first, ending with the trainee's new question. */
    public function ask(Request $request)
    {
        $this->canUse($request);
        abort_unless(Claude::enabled(), 503, 'AI_DISABLED');
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);
        $messages = array_values(array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $data['messages']));
        abort_unless(end($messages)['role'] === 'user' && $messages[0]['role'] === 'user', 422, 'BAD_CONVERSATION');
        abort_unless(CourseMaterial::exists(), 422, 'NO_MATERIALS');

        $user = $request->user();
        if (! $user->isAdmin()) {
            $key = 'ai-q:'.$user->id.':'.now()->toDateString();
            Cache::add($key, 0, now()->endOfDay());
            abort_if(Cache::increment($key) > config('spark.ai.questions_per_day'), 429, 'AI_DAILY_LIMIT');
        }

        return response()->json(['answer' => Claude::ask(Prompts::assistantSystem(), $messages, 1000)]);
    }
}
