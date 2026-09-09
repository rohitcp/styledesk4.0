<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a note's body should be read.
 *
 * Notes used to be plain text, rendered with the newlines preserved. They are
 * rich text now, and the two cannot be told apart by looking: a line break in
 * the old format is a character, in the new one it is a tag.
 *
 * So the row says which it is. Existing notes keep 'text' and keep rendering
 * exactly as they did — escaped, with their line breaks — and only rows
 * written by the new editor are ever rendered as markup. A column of guesses
 * would have meant either losing every old line break or trusting a stored
 * string nobody sanitised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->string('format', 10)->default('text')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
