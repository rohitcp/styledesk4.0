<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shift rule keeps no hours of its own.
     *
     * Working hours are a business-level fact — one configuration, in
     * location_hours, edited at App Settings → Business → Working Hours and
     * read by scheduling, booking, resources and the calendar alike. A rule
     * that stored its own copy was a second answer to the same question, free
     * to drift the moment somebody changed the business's Monday and not the
     * rule's.
     *
     * So the rule now says what a schedule built from it may and may not do —
     * limits, breaks, rest, overtime — and the hours it works within come
     * from the business.
     */
    public function up(): void
    {
        Schema::dropIfExists('shift_rule_periods');
    }

    public function down(): void
    {
        Schema::create('shift_rule_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rule_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->time('starts_at');
            $table->time('ends_at');

            $table->index(['shift_rule_id', 'day_of_week']);
        });
    }
};
