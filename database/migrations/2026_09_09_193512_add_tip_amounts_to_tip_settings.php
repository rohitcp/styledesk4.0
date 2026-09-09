<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The till's flat-sum options, beside its percentages.
 *
 * A business tipping in percentages offers 15/18/20/25; one tipping in flat
 * sums offers $5/$10/$15/$20. Both are "what the client is shown at the
 * till", and both have to be configurable — the second used to have nowhere
 * to live, so a business set to flat sums got a row of percentages it had
 * never chosen.
 *
 * Two columns rather than one read through the type: the business keeps both
 * answers, so switching to amounts and back does not cost somebody the
 * percentages they spent a minute choosing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tip_settings', function (Blueprint $table) {
            $table->json('amounts')->nullable()->after('percentages');
        });
    }

    public function down(): void
    {
        Schema::table('tip_settings', function (Blueprint $table) {
            $table->dropColumn('amounts');
        });
    }
};
