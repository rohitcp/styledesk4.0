<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One person's own answers to questions the business has already answered.
 *
 * Every column here has a counterpart on `tenants`: the business sets the
 * house default and a colleague may differ from it. That is why each is
 * nullable rather than seeded — null means "whatever the business says", and
 * it keeps following the business if that changes, which a copied-down value
 * would not.
 *
 * A table rather than columns on `users` for the same reason booking_settings
 * and client_settings are tables: `users` is the identity record that
 * authentication reads on every request, and a screen's worth of display
 * preferences does not belong in it. The language is the one exception and
 * stays on `users.locale`, where App\Support\Locale and the login-time
 * middleware already read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();

            /* Unique: a person has one set of preferences. The constraint is
               what makes updateOrCreate safe against two tabs saving at once. */
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('date_format', 20)->nullable();
            $table->string('time_format', 4)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedTinyInteger('first_day_of_week')->nullable();

            /* Calendar preferences. Nullable booleans on purpose: false is a
               choice ("hide weekends"), null is "never asked", and a reset
               puts them back to null rather than guessing which. */
            $table->string('calendar_view', 10)->nullable();
            $table->boolean('show_weekends')->nullable();
            $table->boolean('show_cancelled')->nullable();
            $table->boolean('show_resource_color')->nullable();
            $table->boolean('show_staff_color')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
