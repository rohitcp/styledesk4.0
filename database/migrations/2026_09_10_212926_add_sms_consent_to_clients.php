<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this client agreed to, and when they said otherwise.
 *
 * The two switches already on the row say what the business intends to send.
 * These say what the client actually consented to and how anybody knows —
 * which is the part a carrier asks about, and the part that has to survive
 * somebody editing a preference in the profile.
 *
 * An opt-out is a timestamp rather than a flag: "when did they stop" is the
 * question asked when a complaint arrives, and a boolean cannot answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            /* How the consent was obtained: online booking, front desk, the
               client portal, an import. Kept as written text rather than an
               enum because the honest list grows with each way the business
               finds of asking. */
            $table->string('sms_consent_source', 40)->nullable()->after('marketing_sms');
            $table->timestamp('sms_consent_at')->nullable()->after('sms_consent_source');
            /* Where it came from, where there was a browser to record. Null
               for a consent taken over the counter or on the phone. */
            $table->string('sms_consent_ip', 45)->nullable()->after('sms_consent_at');

            $table->timestamp('sms_opted_out_at')->nullable()->after('sms_consent_ip');
            /* STOP, or the desk doing it on the client's behalf. */
            $table->string('sms_opt_out_source', 40)->nullable()->after('sms_opted_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'sms_consent_source', 'sms_consent_at', 'sms_consent_ip',
                'sms_opted_out_at', 'sms_opt_out_source',
            ]);
        });
    }
};
