<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who joins the scheme, and what they are given for joining.
 *
 * Until now every client with points was simply a client who had been to the
 * salon — there was no joining, so there was nothing to decide. A business
 * that wants its clients to opt in has a real reason to: points are a promise
 * to contact somebody about a balance, and a salon in a jurisdiction that
 * treats that as marketing needs the client to have said yes.
 *
 * `default_on` is the default default: ticked for the receptionist, who can
 * untick it. It gets the enrolment rate of automatic while leaving the client
 * a say, and it is the one of the three that is a decision rather than a
 * policy.
 *
 * `welcome_points` is zero until a business says otherwise. A joining bonus
 * is a cost, and StyleDesk does not commit anybody's money for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->string('enrollment_mode', 16)->default('default_on')->after('description');
            $table->unsignedInteger('welcome_points')->default(0)->after('enrollment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->dropColumn(['enrollment_mode', 'welcome_points']);
        });
    }
};
