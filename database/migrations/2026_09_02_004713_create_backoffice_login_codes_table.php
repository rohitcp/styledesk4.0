<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time code that stands in front of the Backoffice sign-in.
 *
 * The password is the second question, not the first. Somebody who has found
 * the address and guessed an email is shown a code box and nothing else — no
 * password field to attack, and no answer to whether that email is an
 * administrator, because the screen says the same thing either way.
 *
 * The code is hashed, like a password. A stolen database dump should not hand
 * over a live second factor, and a code readable in the row is one an
 * administrator with database access could use to sign in as somebody else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_login_codes', function (Blueprint $table) {
            $table->id();

            /* The address it was sent to, not an administrator id: the code is
               issued before anybody is identified, and an unknown address must
               leave a row for the same reason a known one does — the two paths
               have to be indistinguishable from outside. */
            $table->string('email')->index();
            $table->string('code_hash');

            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();

            /* Wrong guesses against this code. A short numeric code is
               guessable at network speed, so the code itself is burnt after a
               few attempts rather than only the connection being throttled. */
            $table->unsignedTinyInteger('attempts')->default(0);

            /* Where it was asked for and from what. Read when an account is
               suspected of being misused, and written for every attempt —
               including the ones that named an address nobody has. */
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_login_codes');
    }
};
