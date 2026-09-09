<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six-digit alternative to clicking the link.
 *
 * Kept on the user rather than in a table of its own: there is only ever one
 * live code per account — issuing a new one replaces the last — so a row per
 * code would be a table that only ever holds what these two columns hold.
 *
 * The code is hashed for the same reason the password is. A readable code in
 * the database is a working credential for anyone who can see the row, and it
 * is short enough that nothing else about it slows an attacker down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_code', 'email_verification_code_expires_at']);
        });
    }
};
