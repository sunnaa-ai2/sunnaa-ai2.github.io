<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\Work;

class Notify
{
    /** Tell a work's owner that someone liked or commented; never for your own work, one like-notice per person. */
    public static function about(Work $work, string $actorId, string $kind): void
    {
        if ($work->user_id === $actorId) {
            return;
        }
        if ($kind === 'like' && AppNotification::where(['user_id' => $work->user_id, 'actor_id' => $actorId, 'work_id' => $work->id, 'kind' => 'like'])->exists()) {
            return;
        }
        AppNotification::create(['user_id' => $work->user_id, 'actor_id' => $actorId, 'kind' => $kind, 'work_id' => $work->id]);
    }
}
