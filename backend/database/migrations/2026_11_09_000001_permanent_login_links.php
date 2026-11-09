<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Login links become permanent: one link per e-mail, kept encrypted so the same link can be sent again.
 * Rows with an expires_at are the old one-time 30-minute links and keep working until they expire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_tokens', function (Blueprint $t) {
            $t->timestamp('expires_at')->nullable()->change();
            $t->text('token_enc')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        DB::table('login_tokens')->whereNull('expires_at')->delete();
        Schema::table('login_tokens', function (Blueprint $t) {
            $t->dropColumn('token_enc');
            $t->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
