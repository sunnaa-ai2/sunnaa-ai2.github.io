<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Exercise;
use App\Models\Invite;
use App\Models\Profile;
use App\Models\Reaction;
use App\Models\Work;

/**
 * Shapes rows exactly like the site already expects them (same field names it used with Supabase),
 * so the frontend switches backends without touching its views.
 */
class Present
{
    public static function profile(Profile $p): array
    {
        return [
            'id' => $p->user_id,
            'full_name' => $p->full_name,
            'bio' => $p->bio,
            'avatar_url' => $p->avatar_path ? route('files.avatar', ['profile' => $p->user_id, 'v' => $p->updated_at?->timestamp]) : null,
            'created_at' => $p->created_at?->toIso8601ZuluString('millisecond'),
        ];
    }

    public static function work(Work $w): array
    {
        return [
            'id' => $w->id,
            'user_id' => $w->user_id,
            'exercise_no' => $w->exercise_no,
            'kind' => $w->kind,
            'url' => $w->kind === 'link' ? $w->url : route('files.work', ['work' => $w->id, 'v' => $w->updated_at?->timestamp]),
            'file_path' => $w->file_name,
            'created_at' => $w->created_at?->toIso8601ZuluString('millisecond'),
            'updated_at' => $w->updated_at?->toIso8601ZuluString('millisecond'),
        ];
    }

    public static function reaction(Reaction $r): array
    {
        return ['work_id' => $r->work_id, 'user_id' => $r->user_id, 'value' => $r->value, 'created_at' => $r->created_at?->toIso8601ZuluString('millisecond')];
    }

    public static function comment(Comment $c): array
    {
        return ['id' => $c->id, 'work_id' => $c->work_id, 'user_id' => $c->user_id, 'body' => $c->body, 'created_at' => $c->created_at?->toIso8601ZuluString('millisecond')];
    }

    public static function exercise(Exercise $e): array
    {
        return ['no' => $e->no, 'title' => $e->title, 'description' => $e->description, 'updated_at' => $e->updated_at?->toIso8601ZuluString('millisecond')];
    }

    public static function invite(Invite $i): array
    {
        return [
            'token' => $i->token, 'email' => $i->email, 'created_by' => $i->created_by,
            'created_at' => $i->created_at?->toIso8601ZuluString('millisecond'),
            'used_at' => $i->used_at?->toIso8601ZuluString('millisecond'), 'used_by' => $i->used_by,
        ];
    }

    public static function notification(AppNotification $n): array
    {
        return [
            'id' => $n->id, 'user_id' => $n->user_id, 'actor_id' => $n->actor_id, 'kind' => $n->kind, 'work_id' => $n->work_id,
            'created_at' => $n->created_at?->toIso8601ZuluString('millisecond'), 'read_at' => $n->read_at?->toIso8601ZuluString('millisecond'),
        ];
    }
}
