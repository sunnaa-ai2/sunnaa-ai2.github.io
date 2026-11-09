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

    private function upload(\App\Models\User $m, array $data)
    {
        return $this->as($m)->post('/api/works', $data, ['Accept' => 'application/json']);
    }

    public function test_member_posts_a_link(): void
    {
        $this->as($this->member('m@x.com'))->postJson('/api/works', ['exercise_no' => 2, 'kind' => 'link', 'url' => 'https://claude.ai'])
            ->assertCreated()->assertJsonPath('kind', 'link')->assertJsonPath('files', []);
    }

    public function test_one_submission_holds_up_to_eight_files_of_any_type(): void
    {
        $m = $this->member('m@x.com');
        $res = $this->upload($m, ['exercise_no' => 3, 'kind' => 'files', 'files' => [
            UploadedFile::fake()->image('poster.jpg', 400, 300),
            UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
        ]])->assertCreated()
            ->assertJsonCount(3, 'files')
            ->assertJsonPath('kind', 'image')               // first file drives the card thumbnail
            ->assertJsonPath('files.0.kind', 'image')
            ->assertJsonPath('files.1.kind', 'pdf')
            ->assertJsonPath('files.2.kind', 'file')
            ->assertJsonPath('files.2.name', 'page.html');

        $this->get(parse_url($res->json('files.0.url'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $html = $this->get(parse_url($res->json('files.2.url'), PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', $html->headers->get('Content-Disposition'));
        // older single-file address still gives the first file
        $this->get('/api/files/works/'.$res->json('id'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson('/api/works')->assertJsonCount(3, '0.files');
    }

    public function test_more_than_eight_files_or_none_is_refused(): void
    {
        $m = $this->member('m@x.com');
        $nine = array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.txt", 1), range(1, 9));
        $this->upload($m, ['exercise_no' => 1, 'kind' => 'files', 'files' => $nine])->assertStatus(422);
        $this->upload($m, ['exercise_no' => 1, 'kind' => 'files'])->assertStatus(422)->assertJsonPath('message', 'FILES_REQUIRED');
        $this->assertSame(0, Work::count());
    }

    public function test_total_size_of_a_submission_is_limited(): void
    {
        config(['spark.max_upload_kb' => 100]);
        $m = $this->member('m@x.com');
        $this->upload($m, ['exercise_no' => 1, 'kind' => 'files', 'files' => [
            UploadedFile::fake()->create('a.zip', 60), UploadedFile::fake()->create('b.zip', 60),
        ]])->assertStatus(422)->assertJsonPath('message', 'FILES_TOO_LARGE');
    }

    public function test_older_single_file_form_still_works(): void
    {
        $m = $this->member('m@x.com');
        $this->upload($m, ['exercise_no' => 1, 'kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg')])
            ->assertCreated()->assertJsonCount(1, 'files');
    }

    public function test_owner_removes_and_adds_files_and_switches_to_a_link(): void
    {
        $m = $this->member('m@x.com');
        $res = $this->upload($m, ['exercise_no' => 1, 'kind' => 'files', 'files' => [
            UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('b.pdf', 5, 'application/pdf'),
        ]])->assertCreated();
        $id = $res->json('id');
        [$a, $b] = [$res->json('files.0.id'), $res->json('files.1.id')];
        $pathA = \App\Models\WorkFile::find($a)->path;

        // remove a.jpg, add two more
        $up = $this->as($m)->post("/api/works/{$id}", ['exercise_no' => 1, 'remove' => [$a], 'files' => [
            UploadedFile::fake()->image('c.png'), UploadedFile::fake()->create('d.docx', 5),
        ]], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(3, 'files');
        $this->assertSame($b, $up->json('files.0.id'));
        $this->assertSame(['b.pdf', 'c.png', 'd.docx'], array_column($up->json('files'), 'name'));
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($pathA);

        // cannot go above 8 or remove everything
        $six = array_map(fn ($i) => UploadedFile::fake()->create("x{$i}.txt", 1), range(1, 6));
        $this->as($m)->post("/api/works/{$id}", ['exercise_no' => 1, 'files' => $six], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('message', 'TOO_MANY_FILES');
        $this->as($m)->post("/api/works/{$id}", ['exercise_no' => 1, 'remove' => array_column($up->json('files'), 'id')], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('message', 'FILES_REQUIRED');

        // to a link: files are deleted; and back to files
        $this->as($m)->postJson("/api/works/{$id}", ['exercise_no' => 1, 'kind' => 'link', 'url' => 'https://a.com'])->assertOk()->assertJsonPath('kind', 'link');
        $this->assertSame(0, \App\Models\WorkFile::count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->allFiles("works/{$id}"));
        $this->as($m)->post("/api/works/{$id}", ['exercise_no' => 1, 'kind' => 'files', 'files' => [UploadedFile::fake()->image('z.jpg')]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('kind', 'image')->assertJsonCount(1, 'files');
    }

    public function test_deleting_a_work_deletes_its_files(): void
    {
        $m = $this->member('m@x.com');
        $id = $this->upload($m, ['exercise_no' => 1, 'kind' => 'files', 'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('b.zip', 3)]])->json('id');
        $this->assertCount(2, \Illuminate\Support\Facades\Storage::disk('local')->allFiles("works/{$id}"));
        $this->as($m)->deleteJson("/api/works/{$id}")->assertOk();
        $this->assertSame(0, \App\Models\WorkFile::count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->allFiles("works/{$id}"));
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
