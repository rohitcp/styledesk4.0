<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this particular service is tipped, and what it suggests.
 *
 * Per service rather than per business, because they are not the same
 * question: a salon that tips its stylists does not tip the shelf a bottle of
 * shampoo came off, and a bill containing both should only offer a tip on the
 * part somebody worked on.
 *
 * Every column is nullable. Null means "whatever the business says" rather
 * than a value of its own — a service that has never been configured should
 * follow the default and keep following it when the default changes, which a
 * copied number would not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('accepts_tips')->nullable()->after('taxable');
            $table->string('tip_type', 16)->nullable()->after('accepts_tips');
            $table->unsignedInteger('tip_value')->nullable()->after('tip_type');
            $table->boolean('tip_required')->nullable()->after('tip_value');
            $table->boolean('allow_no_tip')->nullable()->after('tip_required');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['accepts_tips', 'tip_type', 'tip_value', 'tip_required', 'allow_no_tip']);
        });
    }
};
