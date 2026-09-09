<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deposit per price, rather than one per service.
 *
 * The deposit belongs to the price it is a deposit on: 20% of a £75 cut and
 * 20% of a £120 colour are different amounts, and a service-wide setting
 * cannot say "deposit on the premium price only".
 *
 * `deposit_value` is read through `deposit_type`: minor units when the type
 * is a fixed amount, whole percent when it is a percentage. One column rather
 * than two, because exactly one of them is ever meaningful and a pair invites
 * a row where both are set and disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->boolean('deposit_required')->default(false)->after('price_minor');
            $table->string('deposit_type', 10)->nullable()->after('deposit_required');
            $table->unsignedInteger('deposit_value')->nullable()->after('deposit_type');
        });
    }

    public function down(): void
    {
        Schema::table('service_prices', function (Blueprint $table) {
            $table->dropColumn(['deposit_required', 'deposit_type', 'deposit_value']);
        });
    }
};
