<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second price, for cash.
 *
 * The existing `price_minor` becomes the card price — which is what it always
 * was in practice, since it is what a card payment charges. The new column is
 * what cash charges, and the two are stored separately rather than one being
 * a percentage off the other: a business that wants them equal, or cash
 * *higher*, is not doing anything wrong, and a stored discount could not say
 * so.
 *
 * Nullable, and null means "the same as card". Every service priced before
 * today has one price and should keep charging it whichever way somebody
 * pays; a nought here would quietly make them all free for cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->unsignedInteger('cash_price_minor')->nullable()->after('price_minor');
        });

        Schema::table('booking_services', function (Blueprint $table) {
            /*
             * What each price was on the day.
             *
             * `price_minor` is what was actually charged. These two are what
             * the service cost either way at the time, kept because prices
             * change and last March's booking has to keep saying what last
             * March's prices were.
             */
            $table->unsignedInteger('card_price_minor')->nullable()->after('price_minor');
            $table->unsignedInteger('cash_price_minor')->nullable()->after('card_price_minor');
        });

        Schema::table('bookings', function (Blueprint $table) {
            /* Which price the booking was worked out at. Not the same as how
               it was eventually paid: a booking priced for cash and settled
               by card is a conversation somebody has to have, and this is
               what makes it visible rather than silent. */
            $table->string('priced_for', 8)->default('card')->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('priced_for');
        });

        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn(['card_price_minor', 'cash_price_minor']);
        });

        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropColumn('cash_price_minor');
        });
    }
};
