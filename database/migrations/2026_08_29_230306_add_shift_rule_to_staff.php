<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            /**
             * The working pattern this person is on.
             *
             * The template, not their schedule: assigning a rule says which
             * pattern their dated shifts should be generated from, and
             * generating them is Staff → Staff Schedule's job. Nullable
             * because a rule is optional — plenty of businesses run a rota
             * without one.
             *
             * nullOnDelete rather than cascade: losing a rule must never take
             * the person with it. A rule that anybody is on cannot be deleted
             * anyway — see ShiftRule::isInUse — so this is the belt to that
             * guard's braces.
             */
            $table->foreignId('shift_rule_id')->nullable()->after('role_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_rule_id');
        });
    }
};
