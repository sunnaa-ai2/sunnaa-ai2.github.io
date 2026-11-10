<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Accounts with a username and password. New trainees register and wait as "pending"
 * until an admin approves them. Invites and e-mail login links are retired;
 * login_tokens now only holds password-reset links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 30)->nullable()->unique()->after('id');
            $t->string('password')->nullable()->after('email');
            $t->string('status', 10)->default('approved')->index()->after('password'); // pending | approved
            $t->string('requested_name', 60)->nullable()->after('status');             // name typed when registering
        });

        DB::table('login_tokens')->delete();
        Schema::dropIfExists('invites');
    }

    public function down(): void
    {
        Schema::create('invites', function (Blueprint $t) {
            $t->uuid('token')->primary();
            $t->string('email', 190)->index();
            $t->uuid('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $t->timestamp('used_at')->nullable();
            $t->uuid('used_by')->nullable();
            $t->timestamps();
        });

        DB::table('users')->where('status', 'pending')->delete();
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['username']);
            $t->dropIndex(['status']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['username', 'password', 'status', 'requested_name']);
        });
    }
};
