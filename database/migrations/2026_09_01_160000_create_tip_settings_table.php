<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this business takes tips, and what it suggests.
 *
 * Its own table rather than columns on `tenants`: tipping is a decision about
 * how money is taken, it will grow — percentages per location, presets per
 * staff member — and a business that switches it off should not lose what it
 * had configured. Switching off hides the section; it does not erase it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tip_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Off until somebody turns it on. A salon that has never thought
               about tipping should not be asked about it at the till. */
            $table->boolean('is_enabled')->default(false);

            /* What the client is offered. Stored rather than hardcoded
               because the business will want their own, and a list in code
               is one they would have to ask for. */
            $table->json('percentages')->nullable();

            /* What a service starts with when nobody has said otherwise. */
            $table->string('default_tip_type', 16)->default('percent');
            $table->unsignedInteger('default_tip_value')->default(20);

            /* Whether the client has to answer the question. Answering it
               with "no tip" is still answering it — see the note on
               `allow_no_tip`. */
            $table->boolean('require_selection')->default(false);
            $table->boolean('allow_no_tip')->default(true);

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_settings');
    }
};
