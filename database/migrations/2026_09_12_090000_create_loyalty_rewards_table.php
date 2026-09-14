<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The things a client's points can actually buy.
 *
 * Until now a balance converted to money at one rate and that was the whole
 * scheme: five hundred points was five pounds off, whatever the client was
 * having. That is a discount with extra steps. A catalogue is what makes it a
 * loyalty programme — "a free aromatherapy upgrade" costs the business the
 * margin on an upgrade rather than five pounds of cash, reads to the client
 * as something worth saving for, and can be pointed at the services the
 * business actually wants booked.
 *
 * The conversion rule on `loyalty_settings` stays. It is the floor every
 * scheme needs — a business that wants nothing more than "points off the
 * bill" should not have to build a catalogue to get it — and a reward here is
 * the business saying something more specific than that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* What the client reads on the list they are saving towards, so
               it is their words rather than a generated description: "$10 Off
               Any Service" and "Friday Treat" are both legitimate. */
            $table->string('name', 80);
            $table->string('description', 255)->nullable();

            /* Which kind of reward, from config('loyalty.reward_types'). A
               string rather than an enum column for the same reason `expiry`
               is: the list ships with the code and a database enum is a
               second copy of it that needs a migration to correct. */
            $table->string('type', 32);

            /* What it costs. The one number every reward has, and the one the
               client is counting towards. */
            $table->unsignedInteger('points_required');

            /* What it is worth, in whichever way its type is worth something.
               Both nullable because most types use one or neither: a free
               service is worth the price of that service on the day, which is
               not a number to freeze in a settings row. */
            $table->unsignedInteger('value_minor')->nullable();
            $table->unsignedTinyInteger('percent')->nullable();

            /* The service being given, for the three types that give one. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            /* What it may be spent on: everything, these services, or these
               categories. The ids live in one json column rather than two
               pivot tables — the list is read whole, every time, and never
               queried across. */
            $table->string('scope', 20)->default('all_services');
            $table->json('scope_ids')->nullable();

            /* Off without being deleted. A seasonal reward comes back next
               year, and a deleted one takes its redemption history's meaning
               with it. */
            $table->boolean('is_active')->default(true);

            /* The order the business put them in, which is the order a client
               should read them: cheapest first is not always the story a
               salon wants to tell. */
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
