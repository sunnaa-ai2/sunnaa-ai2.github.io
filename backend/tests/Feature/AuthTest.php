<?php

namespace Tests\Feature;

use App\Mail\NoticeMail;
use App\Models\LoginToken;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private array $form = ['full_name' => 'سارة أحمد', 'username' => 'Sara_A', 'email' => 'Sara@Example.com', 'password' => 'secret-123'];

    private function register(array $over = [])
    {
        return $this->postJson('/api/auth/register', array_merge($this->form, $over));
    }

    private function login(string $login, string $password = 'secret-123')
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->postJson('/api/auth/login', ['login' => $login, 'password' => $password]);
    }

    private function resetToken(string $email): string
    {
        $url = null;
        Mail::assertSent(NoticeMail::class, function (NoticeMail $m) use (&$url, $email) {
            if ($m->hasTo($email) && str_contains((string) $m->buttonUrl, '?reset=')) {
                $url = $m->buttonUrl;
            }

            return true;
        });
        $this->assertNotNull($url, 'no reset e-mail');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q['reset'];
    }

    public function test_registration_waits_for_an_admin_who_is_told_by_e_mail(): void
    {
        Mail::fake();
        $this->register()->assertCreated()->assertJsonPath('status', 'pending');

        $u = User::where('username', 'sara_a')->first();
        $this->assertSame('pending', $u->status);
        $this->assertSame('sara@example.com', $u->email);
        $this->assertNotSame('secret-123', $u->password);   // stored hashed
        Mail::assertSent(NoticeMail::class, fn ($m) => $m->hasTo('admin@test.com') && $m->hasTo('trainer@test.com') && str_contains($m->heading, 'سارة أحمد'));

        // cannot sign in yet
        $this->login('sara_a')->assertForbidden()->assertJsonPath('message', 'PENDING_APPROVAL');
        $this->assertSame(0, Profile::count());
    }

    public function test_admin_approves_then_the_trainee_signs_in_with_username_or_e_mail(): void
    {
        Mail::fake();
        $this->register()->assertCreated();
        $id = User::where('username', 'sara_a')->value('id');

        $admin = $this->user('admin@test.com');
        $this->as($admin)->getJson('/api/me')->assertJsonPath('pending_requests', 1);
        $this->as($admin)->getJson('/api/admin/requests')->assertOk()->assertJsonPath('0.username', 'sara_a')->assertJsonPath('0.full_name', 'سارة أحمد');
        $this->as($admin)->postJson("/api/admin/requests/{$id}/approve")->assertOk()->assertJsonPath('full_name', 'سارة أحمد');
        Mail::assertSent(NoticeMail::class, fn ($m) => $m->hasTo('sara@example.com') && str_contains($m->heading, 'قبول'));
        $this->getJson('/api/profiles')->assertJsonFragment(['full_name' => 'سارة أحمد']);

        $token = $this->login('SARA_A')->assertOk()->assertJsonPath('user.username', 'sara_a')->assertJsonPath('is_admin', false)->json('token');
        $this->login('sara@example.com')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertJsonPath('is_member', true);

        $this->login('sara_a', 'wrong-pass')->assertStatus(422)->assertJsonPath('message', 'LOGIN_FAILED');
        $this->login('nobody', 'whatever1')->assertStatus(422)->assertJsonPath('message', 'LOGIN_FAILED');
    }

    public function test_admin_rejects_and_the_person_may_apply_again(): void
    {
        Mail::fake();
        $this->register()->assertCreated();
        $id = User::where('username', 'sara_a')->value('id');
        $this->as($this->user('admin@test.com'))->postJson("/api/admin/requests/{$id}/reject")->assertOk();
        Mail::assertSent(NoticeMail::class, fn ($m) => $m->hasTo('sara@example.com') && ! $m->buttonUrl);
        $this->assertNull(User::find($id));

        $this->guest()->postJson('/api/auth/register', $this->form)->assertCreated();
    }

    public function test_only_admins_see_and_handle_requests(): void
    {
        $this->register()->assertCreated();
        $id = User::where('username', 'sara_a')->value('id');
        $member = $this->member('m@x.com');
        $this->as($member)->getJson('/api/admin/requests')->assertForbidden();
        $this->as($member)->postJson("/api/admin/requests/{$id}/approve")->assertForbidden();
        $this->as($member)->getJson('/api/me')->assertJsonPath('pending_requests', 0);
    }

    public function test_registration_checks_its_fields(): void
    {
        $this->register(['username' => 'a b'])->assertStatus(422)->assertJsonPath('message', 'USERNAME_INVALID');
        $this->register(['password' => 'short'])->assertStatus(422)->assertJsonPath('message', 'PASSWORD_SHORT');
        $this->register(['email' => 'trainer@test.com'])->assertStatus(422)->assertJsonPath('message', 'EMAIL_RESERVED');
        $this->register()->assertCreated();
        $this->register(['email' => 'other@x.com'])->assertStatus(422)->assertJsonPath('message', 'USERNAME_TAKEN');
        $this->register(['username' => 'other'])->assertStatus(422)->assertJsonPath('message', 'EMAIL_TAKEN');
    }

    public function test_forgotten_password_is_reset_by_an_e_mailed_link_that_works_once(): void
    {
        Mail::fake();
        $u = $this->member('m@x.com');
        $u->forceFill(['username' => 'mona', 'password' => 'old-password'])->save();
        $old = $u->createToken('old')->plainTextToken;

        $this->postJson('/api/auth/forgot', ['email' => 'M@X.com'])->assertOk();
        $tok = $this->resetToken('m@x.com');

        $this->postJson('/api/auth/reset', ['token' => $tok, 'password' => 'new-password'])->assertOk()->assertJsonPath('user.username', 'mona');
        $this->postJson('/api/auth/reset', ['token' => $tok, 'password' => 'again-12345'])->assertStatus(422)->assertJsonPath('message', 'RESET_LINK_INVALID');
        $this->login('mona', 'new-password')->assertOk();
        $this->login('mona', 'old-password')->assertStatus(422);
        // other devices were signed out
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_forgot_says_ok_but_sends_nothing_for_unknown_or_pending_accounts(): void
    {
        Mail::fake();
        $this->register()->assertCreated();
        Mail::fake();   // forget the admin notice
        $this->postJson('/api/auth/forgot', ['email' => 'sara@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot', ['email' => 'ghost@example.com'])->assertOk();
        Mail::assertNothingSent();
        $this->assertSame(0, LoginToken::count());
    }

    public function test_an_admin_opens_their_account_through_a_reset_link(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/forgot', ['email' => 'trainer@test.com'])->assertOk();
        $res = $this->postJson('/api/auth/reset', ['token' => $this->resetToken('trainer@test.com'), 'password' => 'trainer-pass'])
            ->assertOk()->assertJsonPath('is_admin', true);
        $this->app['auth']->forgetGuards();
        $this->withToken($res->json('token'))->putJson('/api/me/username', ['username' => 'Trainer'])->assertOk()->assertJsonPath('username', 'trainer');
        $this->login('trainer', 'trainer-pass')->assertOk()->assertJsonPath('is_admin', true);
    }

    public function test_console_prints_a_reset_link(): void
    {
        $this->artisan('sunnaa:reset-link', ['email' => 'Admin@Test.com'])->assertSuccessful();
        $this->assertSame(1, LoginToken::where('email', 'admin@test.com')->count());
        $this->artisan('sunnaa:reset-link', ['email' => 'nobody@test.com'])->assertFailed();
    }

    public function test_changing_password_needs_the_current_one(): void
    {
        $u = $this->member('m@x.com');
        $u->forceFill(['username' => 'mona', 'password' => 'old-password'])->save();
        $this->as($u)->putJson('/api/me/password', ['current_password' => 'nope', 'password' => 'new-password'])->assertStatus(422)->assertJsonPath('message', 'WRONG_PASSWORD');
        $this->as($u)->putJson('/api/me/password', ['current_password' => 'old-password', 'password' => 'new-password'])->assertOk();
        $this->login('mona', 'new-password')->assertOk();
    }

    public function test_username_must_be_free(): void
    {
        $this->member('a@x.com')->forceFill(['username' => 'taken'])->save();
        $this->as($this->member('b@x.com'))->putJson('/api/me/username', ['username' => 'Taken'])->assertStatus(422)->assertJsonPath('message', 'USERNAME_TAKEN');
    }

    public function test_deleting_the_account_removes_its_reset_links(): void
    {
        Mail::fake();
        $m = $this->member('gone@test.com');
        $this->postJson('/api/auth/forgot', ['email' => 'gone@test.com'])->assertOk();
        $this->assertSame(1, LoginToken::count());
        $this->as($m)->deleteJson('/api/me')->assertOk();
        $this->assertSame(0, LoginToken::count());
    }

    public function test_logout_revokes_the_token(): void
    {
        $u = $this->user('x@y.com');
        $token = $u->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
