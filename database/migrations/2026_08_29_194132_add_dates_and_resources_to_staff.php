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
             * Two dates the staff form asks for and had nowhere to put.
             *
             * Dates, not timestamps: a birthday and a first day are days, and
             * storing them with a time attaches a timezone question to
             * something that has no time in it — the answer to "when did they
             * start" must not change because the business moved server.
             */
            $table->date('date_of_birth')->nullable()->after('pronouns');
            $table->date('started_on')->nullable()->after('employee_ref');
        });

        /**
         * Which chairs, rooms or stations this person works at.
         *
         * The mirror of resource_service: a service says which rooms will do,
         * and this says which of them a given person actually uses. Both are
         * needed to answer "can this appointment happen" — the room has to
         * suit the treatment and the person has to work in it.
         */
        Schema::create('resource_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->unique(['resource_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_staff');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'started_on']);
        });
    }
};
