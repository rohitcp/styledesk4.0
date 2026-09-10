<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What one booking of a service costs in membership credits.
 *
 * A second price, in a second currency. Money and credits are not
 * convertible: a business selling a ninety-minute massage for the same credit
 * as a thirty-minute one is making a decision about its memberships, not a
 * rounding error — so the number is asked for rather than worked out from the
 * price, and changing one never changes the other.
 *
 * One rather than nought by default: every service a membership covers costs
 * at least something to redeem, and a credit usage of nought would be a
 * service a member could book for ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_usage')->default(1)->after('buffer_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('credit_usage');
        });
    }
};
