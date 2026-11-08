<?php

namespace Tests\Feature;

use App\Models\Invite;
use Tests\TestCase;

class JoinTest extends TestCase
{
    public function test_join_needs_an_invite(): void
    {
        $this->as($this->user('evil@x.com'))->postJson('/api/join', ['name' => 'Evil'])->assertStatus(422)->assertJsonPath('message', 'INVITE_REQUIRED');
    }

    public function test_invite_is_bound_to_its_email_and_works_once(): void
    {
        $admin = $this->member('admin@test.com', 'Admin');
        $token = $this->as($admin)->postJson('/api/admin/invites', ['email' => 'Sara@x.com'])->assertCreated()->json('token');

        $this->app['auth']->forgetGuards();
        $this->as($this->user('other@x.com'))->postJson('/api/join', ['token' => $token, 'name' => 'Other'])
            ->assertStatus(422)->assertJsonPath('message', 'INVITE_EMAIL_MISMATCH');

        $this->app['auth']->forgetGuards();
        $sara = $this->user('sara@x.com');
        $this->as($sara)->postJson('/api/join', ['token' => $token, 'name' => 'Sara'])->assertCreated()->assertJsonPath('full_name', 'Sara');
        $this->assertNotNull(Invite::find($token)->used_at);

        $this->app['auth']->forgetGuards();
        $sara->delete();
        $this->as($this->user('sara@x.com'))->postJson('/api/join', ['token' => $token, 'name' => 'Again'])
            ->assertStatus(422)->assertJsonPath('message', 'INVITE_USED');
    }

    public function test_admin_can_join_without_invite(): void
    {
        $this->as($this->user('admin@test.com'))->postJson('/api/join', ['name' => 'Abed'])->assertCreated();
    }

    public function test_only_admins_manage_invites(): void
    {
        $this->as($this->member('m@x.com'))->getJson('/api/admin/invites')->assertForbidden();
        $this->guest()->getJson('/api/admin/invites')->assertUnauthorized();
    }
}
