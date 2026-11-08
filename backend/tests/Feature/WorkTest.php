<?php

namespace Tests\Feature;

use App\Models\Work;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WorkTest extends TestCase
{
    public function test_visitors_see_profiles_and_works_but_cannot_post(): void
    {
        $m = $this->member('m@x.com', 'Mona');
        Work::create(['user_id' => $m->id, 'exercise_no' => 1, 'kind' => 'link', 'url' => 'https://a.com']);
        $this->getJson('/api/profiles')->assertOk()->assertJsonPath('0.full_name', 'Mona');
        $this->getJson('/api/works')->assertOk()->assertJsonPath('0.url', 'https://a.com');
        $this->postJson('/api/works', ['exercise_no' => 1, 'kind' => 'link', 'url' => 'https://b.com'])->assertUnauthorized();
    }

    public function test_signed_in_non_member_cannot_post(): void
    {
        $this->as($this->user('x@y.com'))->postJson('/api/works', ['exercise_no' => 1, 'kind' => 'link', 'url' => 'https://b.com'])
            ->assertForbidden()->assertJsonPath('message', 'NOT_MEMBER');
    }

    public function test_member_posts_link_image_and_file(): void
    {
        $m = $this->member('m@x.com');
        $this->as($m)->postJson('/api/works', ['exercise_no' => 2, 'kind' => 'link', 'url' => 'https://claude.ai'])->assertCreated()->assertJsonPath('kind', 'link');

        $img = $this->as($m)->post('/api/works', ['exercise_no' => 3, 'kind' => 'image', 'file' => UploadedFile::fake()->image('poster.jpg', 400, 300)], ['Accept' => 'application/json'])->assertCreated();
        $this->get(parse_url($img->json('url'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $page = $this->as($m)->post('/api/works', ['exercise_no' => 4, 'kind' => 'file', 'file' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')], ['Accept' => 'application/json'])->assertCreated();
        $res = $this->get(parse_url($page->json('url'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }

    public function test_image_kind_rejects_non_images(): void
    {
        $this->as($this->member('m@x.com'))->post('/api/works', ['exercise_no' => 1, 'kind' => 'image', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_only_owner_or_admin_edits_and_deletes(): void
    {
        $owner = $this->member('o@x.com');
        $work = Work::create(['user_id' => $owner->id, 'exercise_no' => 1, 'kind' => 'link', 'url' => 'https://a.com']);

        $this->as($this->member('other@x.com'))->postJson("/api/works/{$work->id}", ['exercise_no' => 5])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->as($owner)->postJson("/api/works/{$work->id}", ['exercise_no' => 5])->assertOk()->assertJsonPath('exercise_no', 5);
        $this->app['auth']->forgetGuards();
        $this->as($this->member('admin@test.com'))->deleteJson("/api/works/{$work->id}")->assertOk();
        $this->assertDatabaseMissing('works', ['id' => $work->id]);
    }
}
