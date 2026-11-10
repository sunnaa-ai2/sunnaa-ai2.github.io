<?php

namespace Tests;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function user(string $email): User
    {
        return User::firstOrCreate(['email' => $email], ['email_verified_at' => now()]);
    }

    protected function member(string $email, string $name = 'Member'): User
    {
        $u = $this->user($email);
        Profile::create(['user_id' => $u->id, 'full_name' => $name]);

        return $u;
    }

    /** Acts as the user through a real Sanctum bearer token, the way the site does. */
    protected function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($u->createToken('test')->plainTextToken);
    }

    /** Back to an anonymous visitor. */
    protected function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders();
    }
}
