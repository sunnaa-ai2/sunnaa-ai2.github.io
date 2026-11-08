<?php

namespace Tests\Feature;

use App\Mail\LoginLinkMail;
use App\Models\LoginToken;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_link_is_mailed_and_works_once(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/link', ['email' => 'Sara@Example.com'])->assertOk()->assertJson(['ok' => true]);

        $url = null;
        Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('sara@example.com');
        });
        $this->assertStringStartsWith('https://front.test/?login=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $res = $this->postJson('/api/auth/verify', ['token' => $q['login']])->assertOk()->assertJsonPath('user.email', 'sara@example.com')->assertJsonPath('is_admin', false);
        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('is_member', false);

        // the same link cannot be used twice
        $this->postJson('/api/auth/verify', ['token' => $q['login']])->assertStatus(422)->assertJsonPath('message', 'LOGIN_LINK_INVALID');
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

    public function test_console_login_link_works(): void
    {
        $this->artisan('sunnaa:login-link', ['email' => 'Admin@Test.com'])->assertSuccessful();
        $row = LoginToken::latest('id')->first();
        $this->assertSame('admin@test.com', $row->email);
        $this->assertTrue($row->expires_at->isFuture());
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
