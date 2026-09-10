<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The number one client's membership is known by.
 *
 * Not the plan's code. "PKG-20260909-0001" names the product the business
 * sells; this names the thing a particular client bought — and those are
 * different questions with different answers. Two clients on the same plan
 * hold two memberships, and a desk asking "which one are we talking about"
 * cannot be answered by a code they share.
 *
 * Unique within the business, and permanent: it goes on the confirmation,
 * into the ledger and onto whatever the client is handed, so a number that
 * could be reissued would eventually name two things.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_memberships', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->after('membership_plan_id');

            /* Per business rather than globally: two salons both numbering
               their first membership MBR-…-0001 is not a conflict, and a
               global unique index would make one of them wait for the
               other. */
            $table->unique(['tenant_id', 'reference'], 'cm_tenant_reference_unique');
        });

        /* Every membership sold before today gets one, numbered by the day it
           started so the sequence reads as the history it is. Ordered by id
           within the day, because that is the order they were actually
           sold in. */
        $counters = [];

        DB::table('client_memberships')->orderBy('id')->get(['id', 'tenant_id', 'type', 'starts_on', 'created_at'])
            ->each(function ($membership) use (&$counters) {
                $day = substr((string) ($membership->created_at ?? $membership->starts_on), 0, 10);
                $day = str_replace('-', '', $day) ?: date('Ymd');
                $key = $membership->tenant_id.'|'.$day;
                $counters[$key] = ($counters[$key] ?? 0) + 1;

                DB::table('client_memberships')
                    ->where('id', $membership->id)
                    ->update([
                        'reference' => 'MBR-'.$day.'-'.str_pad((string) $counters[$key], 4, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('client_memberships', function (Blueprint $table) {
            $table->dropUnique('cm_tenant_reference_unique');
            $table->dropColumn('reference');
        });
    }
};
