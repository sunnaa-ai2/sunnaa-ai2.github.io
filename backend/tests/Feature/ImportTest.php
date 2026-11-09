<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Tests\TestCase;

class ImportTest extends TestCase
{
    private function file(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, json_encode($data));

        return $path;
    }

    private array $data = [
        'users' => [['id' => 'f88943bb-f816-4b2c-9ace-94c8b0d3a479', 'email' => 'Old@Test.com', 'created_at' => '2026-11-06T18:27:48+00:00']],
        'profiles' => [['id' => 'f88943bb-f816-4b2c-9ace-94c8b0d3a479', 'full_name' => 'عضو قديم', 'bio' => 'نبذة', 'created_at' => '2026-11-06T18:41:57+00:00']],
    ];

    public function test_imports_user_and_profile_keeping_the_old_id(): void
    {
        $this->artisan('sunnaa:import-supabase', ['file' => $this->file($this->data)])->assertSuccessful();

        $u = User::where('email', 'old@test.com')->first();
        $this->assertSame('f88943bb-f816-4b2c-9ace-94c8b0d3a479', $u->id);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('2026-11-06', $u->created_at->toDateString());
        $p = Profile::find($u->id);
        $this->assertSame('عضو قديم', $p->full_name);
        $this->assertSame('نبذة', $p->bio);
        $this->getJson('/api/profiles')->assertOk()->assertJsonFragment(['full_name' => 'عضو قديم']);
    }

    public function test_running_twice_or_over_an_existing_member_creates_no_duplicates(): void
    {
        $existing = $this->member('old@test.com', 'الاسم الجديد');

        $file = $this->file($this->data);
        $this->artisan('sunnaa:import-supabase', ['file' => $file])->assertSuccessful();
        $this->artisan('sunnaa:import-supabase', ['file' => $file])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(1, Profile::count());
        $p = Profile::find($existing->id);
        $this->assertSame('الاسم الجديد', $p->full_name);   // kept
        $this->assertSame('نبذة', $p->bio);                 // empty field filled
    }

    public function test_rejects_invalid_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, 'not json');
        $this->artisan('sunnaa:import-supabase', ['file' => $path])->assertFailed();
    }
}
