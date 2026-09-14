<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * StyleDesk Email on from the start.
 *
 * It defaulted to off, on the reasonable-sounding argument that nothing
 * should send on a business's behalf until they say so. In practice it made
 * the one provider that needs no setting up — StyleDesk's own SMTP, which
 * works on the day somebody signs up — something every business had to go and
 * find before a single email would leave. The Email button on a client
 * profile fell back to a mailto: link until they did, so the feature looked
 * missing rather than switched off.
 *
 * Nothing is sent that was not already going to be sent: this decides whether
 * the desk may write to a client from inside StyleDesk, not whether StyleDesk
 * writes to anybody on its own.
 *
 * Existing businesses are switched on too, and given the default provider
 * where they have none. A business that has been in the product for months
 * without finding this setting is in exactly the position the default was
 * wrong for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('client_email_enabled')->default(true)->change();
        });

        DB::table('tenants')->where('client_email_enabled', false)->update([
            'client_email_enabled' => true,
        ]);

        /* The provider that needs nothing connected, for anybody who never
           chose one. A business that picked Gmail keeps Gmail. */
        DB::table('tenants')->whereNull('email_provider')->update([
            'email_provider' => 'styledesk',
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('client_email_enabled')->default(false)->change();
        });
    }
};
