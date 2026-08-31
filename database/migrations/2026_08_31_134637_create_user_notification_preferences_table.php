<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which messages a person wants, and where.
 *
 * A row per (person, notification, channel) rather than a column per switch:
 * the catalogue in App\Support\NotificationCatalog grows every time a feature
 * learns to notify, and a column-per-type table would need a migration for
 * each. It also makes the two channels that do not exist yet — SMS and push —
 * a matter of writing rows rather than of widening the table.
 *
 * Rows are exceptions, not the state. A person with no rows gets the
 * catalogue's defaults, which is what a new account and a reset both look
 * like: `is_enabled` is only ever written when somebody's answer differs from
 * the default, and the whole row set is deleted to reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* The catalogue key ('booking.created'), not a foreign key: the
               catalogue is code, because what a notification means cannot be
               edited by a row in a table. */
            $table->string('type_key', 60);
            $table->string('channel', 20);
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['user_id', 'type_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
