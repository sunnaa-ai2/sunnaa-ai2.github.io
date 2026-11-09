<?php

namespace Tests\Feature;

use App\Mail\LoginLinkMail;
use App\Models\LoginToken;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function mailedLink(string $email): string
    {
        Mail::fake();
        $this->postJson('/api/auth/link', ['email' => $email])->assertOk()->assertJson(['ok' => true]);
        $url = null;
        Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $m) use (&$url, $email) {
            $url = $m->url;

            return $m->hasTo(strtolower($email));
        });
        $this->assertStringStartsWith('https://front.test/?login=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q['login'];
    }

    public function test_login_link_is_permanent_and_the_same_link_is_resent(): void
    {
        $token = $this->mailedLink('Sara@Example.com');

        $res = $this->postJson('/api/auth/verify', ['token' => $token])->assertOk()->assertJsonPath('user.email', 'sara@example.com')->assertJsonPath('is_admin', false);
        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('is_member', false);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        // works again later (another device), and a new request sends the very same link
        $this->travel(400)->days();
        $this->postJson('/api/auth/verify', ['token' => $token])->assertOk()->assertJsonPath('user.email', 'sara@example.com');
        $this->assertSame($token, $this->mailedLink('sara@example.com'));
        $this->assertSame(1, LoginToken::where('email', 'sara@example.com')->count());
    }

    public function test_old_one_time_links_still_work_only_once(): void
    {
        LoginToken::create(['email' => 'x@y.com', 'token_hash' => hash('sha256', str_repeat('b', 48)), 'expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/auth/verify', ['token' => str_repeat('b', 48)])->assertOk();
        $this->postJson('/api/auth/verify', ['token' => str_repeat('b', 48)])->assertStatus(422)->assertJsonPath('message', 'LOGIN_LINK_INVALID');
    }

    public function test_invite_token_is_carried_in_the_login_link(): void
    {
        Mail::fake();
        $invite = '0b9c1d3e-1f2a-4b5c-8d7e-9f0a1b2c3d4e';
        $this->postJson('/api/auth/link', ['email' => 'a@b.com', 'invite' => $invite])->assertOk();
        Mail::assertSent(LoginLinkMail::class, fn ($m) => str_contains($m->url, '&invite='.$invite));
    }

    public function test_expired_link_is_rejected(): void
    {
        LoginToken::create(['email' => 'x@y.com', 'token_hash' => hash('sha256', str_repeat('a', 48)), 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/auth/verify', ['token' => str_repeat('a', 48)])->assertStatus(422);
    }

    public function test_console_prints_the_permanent_link_and_new_replaces_it(): void
    {
        $this->artisan('sunnaa:login-link', ['email' => 'Admin@Test.com'])->assertSuccessful();
        $row = LoginToken::where('email', 'admin@test.com')->sole();
        $this->assertNull($row->expires_at);
        $old = \Illuminate\Support\Facades\Crypt::decryptString($row->token_enc);
        $this->postJson('/api/auth/verify', ['token' => $old])->assertOk();

        $this->artisan('sunnaa:login-link', ['email' => 'admin@test.com', '--new' => true])->assertSuccessful();
        $this->postJson('/api/auth/verify', ['token' => $old])->assertStatus(422);
        $this->assertSame(1, LoginToken::where('email', 'admin@test.com')->count());
    }

    public function test_deleting_the_account_removes_its_login_link(): void
    {
        $m = $this->member('gone@test.com');
        $token = $this->mailedLink('gone@test.com');
        $this->as($m)->deleteJson('/api/me')->assertOk();
        $this->assertSame(0, LoginToken::where('email', 'gone@test.com')->count());
        $this->guest()->postJson('/api/auth/verify', ['token' => $token])->assertStatus(422);
    }

    public function test_admin_flag_comes_from_admin_emails(): void
    {
        $this->as($this->user('trainer@test.com'))->getJson('/api/me')->assertJsonPath('is_admin', true);
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
