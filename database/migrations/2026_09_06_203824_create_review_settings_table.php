<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this business asks its clients what they thought, and when.
 *
 * Its own table for the same reasons `tip_settings` is: it is a decision about
 * how the business speaks to its clients, it will grow — a delay per location,
 * categories per service — and a salon that switches it off for a difficult
 * month should find its wording exactly as it left it. Switching off stops the
 * asking; it erases nothing, and the reviews already given stay readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* Off until somebody turns it on. A business that has not decided
               how it wants to ask should not start asking by default. */
            $table->boolean('is_enabled')->default(false);

            /* How long after the appointment. An hour by default: long enough
               that the client has left and the visit has settled, short
               enough that they still remember it. */
            $table->string('delay', 16)->default('1h');

            /* Which way to ask. `sms` and `both` are storable now and
               deliverable later — the SMS column in the notification
               catalogue is marked unavailable for the same reason, and a
               setting that quietly refused to save the answer a business gave
               would be worse than one that says "coming soon". */
            $table->string('channel', 10)->default('email');

            /* The extra rating categories — cleanliness, atmosphere, value.
               Off for now, and stored as a list rather than columns because
               which ones a spa cares about is not a decision StyleDesk should
               be making in a migration. */
            $table->json('categories')->nullable();

            /* Whether the happy ones are shown the Google button at all. The
                URL itself lives on the location, because a business with three
                branches has three listings. */
            $table->boolean('google_enabled')->default(true);

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_settings');
    }
};
