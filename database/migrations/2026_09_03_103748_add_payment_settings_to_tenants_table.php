<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a business accepts, and what it asks for up front.
 *
 * `accepted_methods` is null until somebody opens the screen, and null means
 * "whatever the gateway can take" rather than "nothing". A business that has
 * never configured payments must still be able to take cash — an empty list
 * stored eagerly would switch the till off for everybody who never visited
 * this page.
 *
 * The deposit default is the top of the three levels the brief describes:
 * business, then service, then the individual booking. Each overrides the one
 * above it, and this is the one that applies when neither of the others has
 * an opinion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('accepted_methods')->nullable()->after('payments_enabled');

            /* none | fixed | percent. Not an enum: the set is in config, and a
               column that disagrees with it is a migration every time the
               product learns a new way to ask for money. */
            $table->string('default_deposit_type', 20)->nullable()->after('accepted_methods');

            /* Minor units for a fixed amount, whole percent for a percentage.
               One column because only one of them is ever meaningful, and two
               would invite a row where both are set. */
            $table->unsignedInteger('default_deposit_value')->nullable()->after('default_deposit_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['accepted_methods', 'default_deposit_type', 'default_deposit_value']);
        });
    }
};
