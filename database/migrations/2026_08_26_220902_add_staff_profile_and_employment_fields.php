<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile and employment fields the staff directory shows, from §4 and §6.
 *
 * Employment type and provider type are separate from `role`, which is the
 * only reason both exist: role is what the application lets you do, employment
 * is what the business pays you as, and a Booth Renter can hold any role at
 * all. Collapsing them would make "contractor" a permission level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('preferred_name', 100)->nullable()->after('last_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('pronouns', 40)->nullable()->after('preferred_name');
            $table->string('employee_ref', 40)->nullable()->after('job_title');
            $table->text('bio')->nullable()->after('employee_ref');
            $table->string('avatar_path')->nullable()->after('bio');

            $table->string('employment_type', 40)->nullable()->after('avatar_path');
            $table->string('provider_type', 40)->nullable()->after('employment_type');

            /**
             * Specialities as JSON rather than a pivot table.
             *
             * They are a free-form list of labels with no behaviour attached —
             * nothing joins on them, filters aggregate over them at directory
             * scale, and a lookup table would be four more files for a list
             * of words.
             */
            $table->json('specialities')->nullable()->after('provider_type');

            // Archived is a state, not a deletion: §16 requires historical
            // appointments and reports to survive it.
            $table->timestamp('archived_at')->nullable()->after('invite_status');

            $table->string('phone_type', 20)->nullable()->after('phone');
            $table->string('work_email')->nullable()->after('email');
            $table->string('secondary_phone', 32)->nullable()->after('phone_type');
        });

        Schema::table('users', function (Blueprint $table) {
            // §3 shows last login in the directory. Recorded on the user
            // rather than on staff, because it is a fact about the account.
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_name', 'middle_name', 'pronouns', 'employee_ref', 'bio', 'avatar_path',
                'employment_type', 'provider_type', 'specialities', 'archived_at',
                'phone_type', 'work_email', 'secondary_phone',
            ]);
        });
    }
};
