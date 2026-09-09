<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a business numbers its resources, and the constraint that keeps the
 * numbers apart.
 *
 * Two settings rather than one format string: a prefix somebody types
 * ("RES-", "Rsrc-") and how many digits the number is padded to. A single
 * pattern field would have to be parsed to find the number again, and the
 * number is the part that has to be read back and incremented.
 *
 * Both nullable, meaning "whatever config/resources.php says" — the same
 * arrangement as every other tenant setting, so a business that never opens
 * the screen follows the product's default and keeps following it.
 *
 * Written to be safe to run twice. The first version was not, and it failed
 * on live data part-way through: the columns were added, the unique index was
 * refused because two resources already shared a code, and because the
 * migration had not finished it was never recorded — so the next run failed
 * again, this time on the columns it had already added. A migration that
 * changes two things has to assume it may be resumed after either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'resource_code_prefix')) {
                $table->string('resource_code_prefix', 12)->nullable()->after('shift_rules_enabled');
            }

            if (! Schema::hasColumn('tenants', 'resource_code_padding')) {
                $table->unsignedTinyInteger('resource_code_padding')->nullable()->after('resource_code_prefix');
            }
        });

        $this->settleDuplicateCodes();

        /**
         * Two resources in one business may not share a code.
         *
         * Enforced here as well as in the request rules, because the code is
         * generated: two people adding a resource at the same moment can both
         * be handed RES-004, and only the database can settle which of them
         * keeps it. ResourceController catches the refusal and takes the next
         * number.
         *
         * Codes are nullable and MySQL allows any number of NULLs in a unique
         * index, so a business that numbers nothing is unaffected.
         */
        if (! Schema::hasIndex('resources', 'resources_tenant_code_unique')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'resources_tenant_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('resources', 'resources_tenant_code_unique')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->dropUnique('resources_tenant_code_unique');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['resource_code_prefix', 'resource_code_padding']);
        });
    }

    /**
     * Make room for the constraint without throwing anything away.
     *
     * Codes were free text until now, so a business can genuinely have two
     * chairs labelled RES-0001 — and one of them has to change before the
     * index can exist. The oldest row keeps the code, because it is the one
     * whose label has been read the longest; the others are suffixed rather
     * than blanked, so whoever tidies up can still see what the code was and
     * which two rows were involved.
     *
     * Deliberately not "generate the next free code for them": a migration
     * that renumbers somebody's chairs into a scheme they have not chosen yet
     * is a bigger change than the one being asked for here.
     */
    private function settleDuplicateCodes(): void
    {
        /* An empty code is not a code. Left as '' it would also break the
           index, because MySQL allows many NULLs in a unique index and only
           one empty string. */
        DB::table('resources')->where('code', '')->update(['code' => null]);

        $duplicates = DB::table('resources')
            ->select('tenant_id', 'code')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->groupBy('tenant_id', 'code')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('resources')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('code', $duplicate->code)
                ->orderBy('id')
                ->pluck('id');

            /* The first keeps what it has; every other one is moved out of
               the way, checking each candidate rather than assuming it is
               free — "RES-0001-2" may itself already be somebody's label. */
            /* values(), because skip() keeps the original keys and the
               suffix would start at 3 for the second row. */
            foreach ($rows->skip(1)->values() as $offset => $id) {
                DB::table('resources')->where('id', $id)->update([
                    'code' => $this->freeSuffix($duplicate->tenant_id, (string) $duplicate->code, $offset + 2),
                ]);
            }
        }
    }

    /** The original code plus the first suffix nothing else in the business holds. */
    private function freeSuffix(string $tenantId, string $code, int $from): ?string
    {
        for ($suffix = $from; $suffix < $from + 100; $suffix++) {
            /* 40 characters is the column, so the original is trimmed rather
               than the suffix dropped: the suffix is the part that makes it
               unique, and a truncated suffix is a collision. */
            $candidate = mb_substr($code, 0, 40 - mb_strlen('-'.$suffix)).'-'.$suffix;

            $taken = DB::table('resources')
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        /* Nothing in a hundred tries, which means the data is stranger than
           this migration can reason about. The code is cleared rather than
           the deployment blocked: a missing code is a gap somebody can fill
           in from the screen, and a failed migration is a site that will not
           start. Null rather than '', because the index allows many nulls and
           exactly one empty string. */
        return null;
    }
};
