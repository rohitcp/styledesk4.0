<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private notes, and who may read them.
 *
 * A private note is not a note with a flag on it — it is a note with an
 * audience. The flag alone could not answer "may this person read it", so the
 * audience is a table: one row per person the author named.
 *
 * Owners and Admins are deliberately not written into that table. Their
 * access comes from their role, and rows would go stale the moment someone
 * is promoted or steps down — the list would then say who could read the note
 * on the day it was written rather than who can read it now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_important');
        });

        Schema::create('client_note_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_note_id')->constrained()->cascadeOnDelete();

            /**
             * Cascade, unlike the note's own author.
             *
             * A note keeps its body when its author leaves, because the
             * warning outlives them. An access row is the opposite: it exists
             * only to answer a question about a person, and once that person
             * is gone the row can only mislead.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['client_note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_note_access');

        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
