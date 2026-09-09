<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dialling code for each number a staff record holds.
 *
 * The three numbers — personal, secondary, emergency — were bare text boxes,
 * so a member of staff who wrote "07700 900461" was recorded with a number
 * nobody outside the UK could dial and nothing saying where it was from. Now
 * that each is entered with a searchable country picker beside it, each needs
 * somewhere to keep the answer; without the column the picker would appear to
 * work and quietly discard what was chosen.
 *
 * Guarded and column-by-column: a migration that adds three things has to be
 * safe to resume after failing on any of them.
 */
return new class extends Migration
{
    /** The number, and the column that carries its dialling code. */
    private const COUNTRIES = [
        'phone' => 'phone_country',
        'secondary_phone' => 'secondary_phone_country',
        'emergency_contact_phone' => 'emergency_contact_phone_country',
    ];

    public function up(): void
    {
        foreach (self::COUNTRIES as $number => $country) {
            if (Schema::hasColumn('staff', $country)) {
                continue;
            }

            Schema::table('staff', function (Blueprint $table) use ($number, $country) {
                $table->char($country, 2)->nullable()->after($number);
            });
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(array_values(self::COUNTRIES));
        });
    }
};
