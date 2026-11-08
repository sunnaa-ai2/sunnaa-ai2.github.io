<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Work;
use Tests\TestCase;

class SocialTest extends TestCase
{
    private function setUpWork(): array
    {
        $owner = $this->member('owner@x.com', 'Owner');
        $fan = $this->member('fan@x.com', 'Fan');
        $work = Work::create(['user_id' => $owner->id, 'exercise_no' => 1, 'kind' => 'link', 'url' => 'https://a.com']);

        return [$owner, $fan, $work];
    }

    public function test_dislikes_are_hidden_from_everyone_but_owner_actor_and_admins(): void
    {
        [$owner, $fan, $work] = $this->setUpWork();
        $this->as($fan)->putJson("/api/works/{$work->id}/reaction", ['value' => -1])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->guest()->getJson('/api/reactions')->assertOk()->assertJsonCount(0);             // visitor
        $this->as($owner)->getJson('/api/reactions')->assertJsonCount(1);                      // owner
        $this->app['auth']->forgetGuards();
        $this->as($this->member('third@x.com'))->getJson('/api/reactions')->assertJsonCount(0); // someone else
        $this->app['auth']->forgetGuards();
        $this->as($this->user('admin@test.com'))->getJson('/api/reactions')->assertJsonCount(1); // admin
    }

    public function test_like_and_comment_notify_the_owner_once(): void
    {
        [$owner, $fan, $work] = $this->setUpWork();
        $this->as($fan)->putJson("/api/works/{$work->id}/reaction", ['value' => 1]);
        $this->as($fan)->putJson("/api/works/{$work->id}/reaction", ['value' => 0]);
        $this->as($fan)->putJson("/api/works/{$work->id}/reaction", ['value' => 1]);
        $this->as($fan)->postJson("/api/works/{$work->id}/comments", ['body' => 'رائع'])->assertCreated();
        $this->assertSame(2, AppNotification::where('user_id', $owner->id)->count());

        $this->app['auth']->forgetGuards();
        $this->as($owner)->postJson("/api/works/{$work->id}/comments", ['body' => 'شكرًا'])->assertCreated();
        $this->assertSame(2, AppNotification::where('user_id', $owner->id)->count()); // no self-notification

        $this->as($owner)->getJson('/api/notifications')->assertJsonCount(2);
        $this->as($owner)->postJson('/api/notifications/read')->assertOk();
        $this->assertSame(0, AppNotification::where('user_id', $owner->id)->whereNull('read_at')->count());
    }

    public function test_only_author_or_admin_deletes_a_comment(): void
    {
        [$owner, $fan, $work] = $this->setUpWork();
        $id = $this->as($fan)->postJson("/api/works/{$work->id}/comments", ['body' => 'hi'])->json('id');
        $this->app['auth']->forgetGuards();
        $this->as($owner)->deleteJson("/api/comments/{$id}")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->as($fan)->deleteJson("/api/comments/{$id}")->assertOk();
    }

    public function test_exercises_are_public_and_admin_edited(): void
    {
        $this->as($this->member('m@x.com'))->putJson('/api/admin/exercises/2', ['title' => 'x'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->as($this->user('admin@test.com'))->putJson('/api/admin/exercises/2', ['title' => 'برومبت', 'description' => 'اكتب برومبت'])->assertOk();
        $this->getJson('/api/exercises')->assertOk()->assertJsonPath('0.title', 'برومبت');
    }
}
