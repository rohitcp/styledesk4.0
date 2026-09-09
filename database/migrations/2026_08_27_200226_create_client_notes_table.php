<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes on a client, one row each.
 *
 * Separate from `clients.notes`, which is the single profile note the business
 * can switch on in App Settings and which the client form collects. This is
 * the running list the profile's Notes tab shows: who wrote it, when, and
 * whether it is the kind of thing the next person must read before touching
 * the client's hair.
 *
 * `created_by` is nullOnDelete rather than cascade. A stylist leaving the
 * salon must not take their notes with them — a note saying "sensitive scalp"
 * matters more than knowing who typed it, and losing the row would be losing
 * the warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_notes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            /**
             * Important notes are shown on the profile itself, not only in
             * the tab: a warning nobody sees before the appointment is a
             * warning that did not happen.
             */
            $table->boolean('is_important')->default(false);

            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notes');
    }
};
