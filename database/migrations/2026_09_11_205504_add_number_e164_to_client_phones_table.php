<?php

declare(strict_types=1);

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The second form of a phone number: the one it is compared in.
 *
 * `number` keeps what the receptionist typed, because that is what they read
 * back down the phone and no screen should start showing "+19735551234" at
 * them. But two records are the same person on the number, and answering that
 * meant stripping punctuation in SQL on both sides of every comparison — a
 * `like '%'` scan that no index could help, and one that called
 * "+1 973 555 1234" and "(973) 555-1234" different people whenever the
 * country code was written on only one of them.
 *
 * So the canonical form is stored beside the typed one and the matching runs
 * on that: an exact, indexed comparison, and one answer about which numbers
 * are the same number.
 *
 * Backfilled rather than left null, because the duplicate check reads it from
 * the day this runs — a null here would quietly stop matching every client
 * already on file. Rows whose number cannot be normalised keep a null and go
 * on being compared the old way; that is the point of keeping the fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phones', function (Blueprint $table) {
            $table->string('number_e164', 20)->nullable()->after('number');

            /* Not unique: two people genuinely share a number — a couple, a
               salon's own landline on a staff record — and the duplicate
               check is a warning everywhere else in this application. */
            $table->index(['tenant_id', 'number_e164']);
        });

        DB::table('client_phones')
            ->select('id', 'number', 'country')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $e164 = PhoneNumber::normalise($row->number, $row->country);

                    if ($e164 !== null) {
                        DB::table('client_phones')->where('id', $row->id)->update(['number_e164' => $e164]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('client_phones', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'number_e164']);
            $table->dropColumn('number_e164');
        });
    }
};
