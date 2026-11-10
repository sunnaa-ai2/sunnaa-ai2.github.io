<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * grades:           the trainer's private mark (0-10) and comment on a submission
 * ai_reviews:       Claude's instant feedback on a submission (seen by its owner and admins)
 * course_materials: lecture summaries the course assistant answers from
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $t) {
            $t->uuid('work_id')->primary();
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->unsignedTinyInteger('score');
            $t->text('comment')->nullable();
            $t->uuid('graded_by')->nullable();
            $t->foreign('graded_by')->references('id')->on('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('ai_reviews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('work_id');
            $t->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
            $t->uuid('user_id');
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->text('body');
            $t->string('model', 60);
            $t->timestamps();
            $t->index(['work_id', 'created_at']);
            $t->index(['user_id', 'created_at']);
        });

        Schema::create('course_materials', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('title', 120);
            $t->longText('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
        Schema::dropIfExists('ai_reviews');
        Schema::dropIfExists('grades');
    }
};
