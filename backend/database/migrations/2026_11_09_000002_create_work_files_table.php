<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * A work (one exercise submission) can now hold up to 8 files.
 * works.kind becomes "files" or "link"; the single file of older works moves into work_files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_files', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('work_id');
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->unsignedTinyInteger('position')->default(0);
            $t->string('kind', 10);               // image | pdf | file
            $t->string('path');                   // path on the files disk
            $t->string('name', 160);              // original name, for display / download
            $t->string('mime', 120);
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->index(['work_id', 'position']);
        });

        foreach (DB::table('works')->whereNotNull('file_path')->get() as $w) {
            DB::table('work_files')->insert([
                'id' => (string) Str::uuid(),
                'work_id' => $w->id,
                'position' => 0,
                'kind' => in_array($w->kind, ['image', 'pdf'], true) ? $w->kind : 'file',
                'path' => $w->file_path,
                'name' => $w->file_name ?: basename($w->file_path),
                'mime' => $w->mime ?: 'application/octet-stream',
                'size' => 0,
                'created_at' => $w->created_at,
                'updated_at' => $w->updated_at,
            ]);
        }
        DB::table('works')->where('kind', '!=', 'link')->update(['kind' => 'files']);

        Schema::table('works', function (Blueprint $t) {
            $t->dropColumn(['file_path', 'file_name', 'mime']);
        });
    }

    public function down(): void
    {
        Schema::table('works', function (Blueprint $t) {
            $t->string('file_path')->nullable();
            $t->string('file_name', 160)->nullable();
            $t->string('mime', 120)->nullable();
        });
        foreach (DB::table('work_files')->orderBy('position')->get()->unique('work_id') as $f) {
            DB::table('works')->where('id', $f->work_id)->update(['kind' => $f->kind, 'file_path' => $f->path, 'file_name' => $f->name, 'mime' => $f->mime]);
        }
        Schema::dropIfExists('work_files');
    }
};
