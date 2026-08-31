<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a business numbers its resources.
 *
 * Two settings rather than one format string: a prefix somebody types
 * ("RES-", "Rsrc-") and how many digits the number is padded to. A single
 * pattern field would have to be parsed to find the number again, and the
 * number is the part that has to be read back and incremented.
 *
 * Both nullable, meaning "whatever config/resources.php says" — the same
 * arrangement as every other tenant setting, so a business that never opens
 * the screen follows the product's default and keeps following it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('resource_code_prefix', 12)->nullable()->after('shift_rules_enabled');
            $table->unsignedTinyInteger('resource_code_padding')->nullable()->after('resource_code_prefix');
        });

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
        Schema::table('resources', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'resources_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropUnique('resources_tenant_code_unique');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['resource_code_prefix', 'resource_code_padding']);
        });
    }
};
