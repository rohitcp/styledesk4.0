<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's phone numbers and email addresses, one row each.
 *
 * The `clients.mobile` and `clients.email` columns stay: they hold the
 * primary of each, written by the model whenever the collection changes.
 * Keeping them means search, the listing, duplicate detection and every
 * future notification read one column rather than each deciding for itself
 * which of five numbers to ring — and a denormalised copy maintained in one
 * place cannot drift the way five independent lookups can.
 *
 * Type and priority are separate columns rather than one, because "work" and
 * "the number to ring first" are different facts about the same number. A
 * client whose work mobile is the one they answer is not representable if
 * "primary" is a type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_phones', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            /**
             * The number as typed, and the country it was typed for.
             *
             * Kept apart because validating a number needs to know which
             * country's rules to apply, and "+1 202-555-1043" read on its own
             * does not say whether the leading digits are a country code or
             * an area code.
             */
            $table->char('country', 2)->nullable();
            $table->string('number', 32);
            $table->string('type', 20)->default('mobile');

            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // The same number twice on one client is a mistake, not a record.
            $table->unique(['client_id', 'number']);
            $table->index(['tenant_id', 'number']);
        });

        Schema::create('client_emails', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->string('type', 20)->default('personal');

            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'email']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_emails');
        Schema::dropIfExists('client_phones');
    }
};
