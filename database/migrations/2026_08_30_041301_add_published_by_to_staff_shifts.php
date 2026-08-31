<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who told the staff member.
 *
 * Publishing is the act that turns a draft into somebody's official working
 * week, and "who decided this" is the first question asked when a rota is
 * disputed. Nullable because every row written before this column existed was
 * published by nobody the database can name, and because the publisher's
 * account may later be deleted — the schedule outlives the employment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->foreignId('published_by')->nullable()->after('published_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
        });
    }
};
