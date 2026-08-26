<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /**
             * Path on the `brand` disk, not a URL.
             *
             * A stored URL bakes in the scheme and host, which then go stale
             * the moment the app moves domain or moves to object storage. The
             * disk knows how to turn a path into a URL; the row should not.
             */
            $table->string('avatar_path')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
