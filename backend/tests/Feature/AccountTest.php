<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_profile_update_with_avatar(): void
    {
        $m = $this->member('m@x.com');
        $res = $this->as($m)->post('/api/me/profile', ['full_name' => 'منى', 'bio' => 'مصممة', 'avatar' => UploadedFile::fake()->image('me.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('full_name', 'منى');
        $this->assertNotNull($res->json('avatar_url'));
        $this->get(parse_url($res->json('avatar_url'), PHP_URL_PATH))->assertOk();
    }

    public function test_deleting_my_account_removes_everything(): void
    {
        $m = $this->member('m@x.com');
        $workId = $this->as($m)->post('/api/works', ['exercise_no' => 1, 'kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->json('id');
        $path = Work::find($workId)->files()->first()->path;
        Storage::disk('local')->assertExists($path);
        $this->as($m)->postJson("/api/works/{$workId}/comments", ['body' => 'x']);

        $this->as($m)->deleteJson('/api/me')->assertOk();
        $this->assertNull(User::find($m->id));
        $this->assertSame(0, Work::count());
        $this->assertSame(0, Comment::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_admin_removes_a_member_but_not_themself(): void
    {
        $admin = $this->member('admin@test.com');
        $m = $this->member('m@x.com');
        $this->as($admin)->deleteJson("/api/admin/members/{$admin->id}")->assertStatus(422);
        $this->as($admin)->deleteJson("/api/admin/members/{$m->id}")->assertOk();
        $this->assertNull(User::find($m->id));
    }
}
