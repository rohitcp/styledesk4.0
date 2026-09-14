<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What state a membership of the rewards scheme is in.
 *
 * The joining date alone said only "member" or "not", which was the whole
 * truth while joining was the only thing that could happen to one. It is not
 * any more: a business needs to be able to pause somebody who is disputing a
 * balance, suspend one it is investigating, and let a client leave without
 * erasing that they were ever in it.
 *
 * Still derived-from-null for the fourth state: `loyalty_enrolled_at` null is
 * "never joined" and has no status at all. This column only says what became
 * of a membership that exists, which is why it is nullable rather than
 * defaulted — a client who never joined has no state to be in.
 *
 * Unenrolled keeps the joining date and the member number on purpose. They
 * are what makes rejoining the same membership rather than a new one, and
 * what keeps a balance readable after somebody leaves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('loyalty_status', 16)->nullable()->after('loyalty_enrolled_at');
        });

        /* Everybody already on file joined and nothing has happened to them
           since, so they are active. Written rather than defaulted: the
           column is nullable for the never-joined, and a default would give
           those a status too. */
        DB::table('clients')
            ->whereNotNull('loyalty_enrolled_at')
            ->update(['loyalty_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('loyalty_status');
        });
    }
};
