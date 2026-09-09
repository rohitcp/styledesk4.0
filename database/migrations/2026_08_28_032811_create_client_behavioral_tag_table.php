<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which behavioural tags a client carries.
 *
 * The catalogue lives in config and a business chooses which of it applies;
 * this is the third question — which ones this particular client has earned.
 *
 * Keyed by the catalogue key rather than by the settings row, so an
 * assignment survives a business switching a tag off and on again: the tag a
 * client earned is a fact about the client, not about the setting.
 *
 * `assigned_manually` marks the ones a person put on by hand. The rule engine
 * that arrives with bookings will write this table too, and it must be able
 * to tell its own work from a decision someone made — recalculating is
 * allowed to remove what it added and never what a human did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_behavioral_tag', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('tag_key', 60);
            $table->boolean('assigned_manually')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'tag_key']);
            $table->index(['tenant_id', 'tag_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_behavioral_tag');
    }
};
