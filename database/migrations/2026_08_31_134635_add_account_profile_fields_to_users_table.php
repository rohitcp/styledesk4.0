<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parts of a person that belong to the person, not to the job.
 *
 * `staff` already holds a job title, a phone number and a photo, and those
 * are the employment record: an administrator maintains them from Staff
 * Management, and they describe somebody's role in one business. These are
 * the account's own — what the signed-in person calls themselves and how the
 * product reaches them — which is why My Account writes here and never to the
 * staff row it does not own.
 *
 * The pending-email columns are what makes an address change safe: the new
 * address is parked until it has been proved, and `email` keeps working the
 * whole time. Nothing reads `pending_email` for identity — it is a claim, not
 * a credential, until verification moves it across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('last_name');
            $table->string('job_title', 100)->nullable()->after('display_name');
            $table->string('phone', 32)->nullable()->after('job_title');
            $table->char('phone_country', 2)->nullable()->after('phone');

            /* Not unique. Two people may both be part-way through claiming the
               same address; only the one who proves it first gets it, and the
               other is refused at that moment against `email`, which is where
               the constraint that matters already lives. */
            $table->string('pending_email')->nullable()->after('email_verified_at');

            /* The hash of the link's token, never the token. A leaked backup
               of this table must not be a set of working change-email links. */
            $table->string('pending_email_token', 64)->nullable()->after('pending_email');
            $table->timestamp('pending_email_expires_at')->nullable()->after('pending_email_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'job_title',
                'phone',
                'phone_country',
                'pending_email',
                'pending_email_token',
                'pending_email_expires_at',
            ]);
        });
    }
};
