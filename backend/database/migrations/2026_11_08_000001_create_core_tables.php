<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('email', 190)->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('login_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->index();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        // A profile means "member": it is created only by joining with an invite (or by an admin).
        Schema::create('profiles', function (Blueprint $t) {
            $t->uuid('user_id')->primary();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->string('full_name', 60);
            $t->string('bio', 300)->nullable();
            $t->string('avatar_path')->nullable();
            $t->timestamps();
        });

        Schema::create('invites', function (Blueprint $t) {
            $t->uuid('token')->primary();
            $t->string('email', 190)->index();
            $t->uuid('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $t->timestamp('used_at')->nullable();
            $t->uuid('used_by')->nullable();
            $t->timestamps();
        });

        Schema::create('works', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('user_id');
            $t->foreign('user_id')->references('user_id')->on('profiles')->cascadeOnDelete();
            $t->unsignedSmallInteger('exercise_no')->index();
            $t->string('kind', 10); // image | pdf | file | link
            $t->string('url', 2048)->nullable();      // only for links
            $t->string('file_path')->nullable();      // path on the files disk
            $t->string('file_name', 160)->nullable(); // original name, for display / download
            $t->string('mime', 120)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });

        Schema::create('reactions', function (Blueprint $t) {
            $t->id();
            $t->uuid('work_id');
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->uuid('user_id');
            $t->foreign('user_id')->references('user_id')->on('profiles')->cascadeOnDelete();
            $t->smallInteger('value'); // 1 like, -1 dislike
            $t->timestamps();
            $t->unique(['work_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('work_id');
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->uuid('user_id');
            $t->foreign('user_id')->references('user_id')->on('profiles')->cascadeOnDelete();
            $t->string('body', 1000);
            $t->timestamps();
            $t->index(['work_id', 'created_at']);
        });

        Schema::create('exercises', function (Blueprint $t) {
            $t->unsignedSmallInteger('no')->primary();
            $t->string('title', 120)->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('user_id');
            $t->foreign('user_id')->references('user_id')->on('profiles')->cascadeOnDelete();
            $t->uuid('actor_id')->nullable();
            $t->foreign('actor_id')->references('user_id')->on('profiles')->cascadeOnDelete();
            $t->string('kind', 10); // like | comment
            $t->uuid('work_id')->nullable();
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['app_notifications', 'exercises', 'comments', 'reactions', 'works', 'invites', 'profiles', 'login_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
