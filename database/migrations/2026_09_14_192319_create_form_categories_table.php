<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a business files its forms.
 *
 * Rows rather than a fixed list in config, because the nine StyleDesk ships
 * with are a starting point and not the vocabulary: a tattoo studio wants
 * "Aftercare" where a med spa wants "Pre-Treatment", and a business that
 * cannot add one ends up with every form under "General".
 *
 * Disabled rather than deleted, for the same reason a retired service is:
 * while any form is filed under a category, that row is where the form's
 * label is read from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60);

            /* Which of the shipped categories this row started as, or null
               for one the business invented. It is what lets the default
               nine be translated — a business's own wording never is — and
               what stops a second seeding creating them all again. */
            $table->string('key', 40)->nullable();

            /* Hand-set, because these are read as a list a business arranged
               rather than found alphabetically. */
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_categories');
    }
};
