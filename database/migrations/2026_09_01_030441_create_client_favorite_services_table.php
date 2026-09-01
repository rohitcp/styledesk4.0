<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The services a client is known to want.
 *
 * Deliberately not the same thing as what they have booked. A service history
 * is arithmetic — six balayages, last one in August — and it is already
 * derivable from the diary. This is a statement somebody made: "this is what
 * she always has", said at the desk and written down, and it stays true for a
 * client who has never booked anything here at all.
 *
 * Folding the two together is the obvious mistake and the expensive one: a
 * favourite that appeared because somebody booked a service twice is a
 * favourite nobody chose, and the receptionist who trusts the list stops
 * being able to tell which is which.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_favorite_services', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            /* Who said so. A preference nobody's name is against is one
               nobody can be asked about. */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* One row per client per service: marking a favourite twice is
               the same statement, not a second one. */
            $table->unique(['client_id', 'service_id']);
            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_favorite_services');
    }
};
