<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How this business sends email to its clients.
 *
 * Off until somebody turns it on. A salon that has not configured a sender
 * should not be able to put mail in a client's inbox by accident, so the
 * feature is opted into rather than out of.
 *
 * `email_reply_to` is the one that matters most in MVP: StyleDesk Email sends
 * as "Smile Spa via StyleDesk", and without a reply-to a client pressing
 * Reply writes to nobody. It is optional because a business without one is
 * still better served by sending than by being blocked.
 *
 * Real columns rather than the `data` blob, and listed in
 * Tenant::getCustomColumns() — the base tenant model sweeps anything
 * undeclared into JSON where nothing can index or query it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('client_email_enabled')->default(false)->after('booking_email');
            $table->string('email_provider', 20)->nullable()->after('client_email_enabled');
            $table->string('email_sender_name')->nullable()->after('email_provider');
            $table->string('email_reply_to')->nullable()->after('email_sender_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'client_email_enabled', 'email_provider', 'email_sender_name', 'email_reply_to',
            ]);
        });
    }
};
