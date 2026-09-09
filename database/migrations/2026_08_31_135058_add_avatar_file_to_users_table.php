<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account photo, as a stored_files row rather than a path.
 *
 * `avatar_path` stays where it is: it is what the older screens wrote and
 * what a staff record still carries, and rewriting those is not this
 * feature's business. What My Account uploads goes through TenantStorage,
 * which is what gives a file a tenant, an owner, a size and a way to be
 * served to the right people — a bare path has none of those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('avatar_file_id')->nullable()->after('avatar_path')
                ->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_file_id');
        });
    }
};
