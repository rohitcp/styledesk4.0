<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which processor this business takes card payments through.
 *
 * Null means none is connected, and the manual gateway answers — money that
 * arrived by other means is still recorded, so a salon that never connects a
 * processor has a complete transaction history rather than an empty one.
 *
 * The connected account's own id lives here too. It is a reference, never a
 * credential: StyleDesk stores what the processor calls the account and
 * nothing that could move money on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payment_gateway', 20)->nullable()->after('client_email_enabled');
            $table->string('payment_account_id')->nullable()->after('payment_gateway');
            $table->boolean('payments_enabled')->default(true)->after('payment_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['payment_gateway', 'payment_account_id', 'payments_enabled']);
        });
    }
};
