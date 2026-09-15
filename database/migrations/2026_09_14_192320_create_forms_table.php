<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A form the business asks its clients to fill in.
 *
 * This row is the form's identity and its rules — what it is called, what
 * kind of thing it is, who has to complete it and how long a completed one
 * stays good for. What it actually *asks* is not here: questions live on
 * `form_versions`, because a business that edits a form with submissions
 * against it must not change the questions those answers were given to.
 *
 * Which services and locations a form applies to are json id lists rather
 * than pivot tables, following `loyalty_rewards.scope_ids`: the lists are
 * read whole every time a booking is evaluated and are never queried across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            /* The client reads this at the top of the form, so it is the
               business's words: "Massage Therapy Intake", not a slug. */
            $table->string('name', 120);

            /* For the business, never shown to the client — why this form
               exists and when to use it, for the person who inherits the
               settings screen. */
            $table->string('internal_description', 255)->nullable();

            /* Intake, consent, waiver, medical and the rest, from
               config('forms.types'). A string rather than a database enum for
               the reason `loyalty_rewards.type` is one: the list ships with
               the code, and an enum column is a second copy needing a
               migration to correct. */
            $table->string('type', 32);

            /* Nullable because a category can be disabled and a form must not
               vanish with it. The list renders those as uncategorised. */
            $table->foreignId('category_id')->nullable()
                ->constrained('form_categories')->nullOnDelete();

            /* Draft, active, inactive, archived. Only an active form is ever
               assigned to anybody. */
            $table->string('status', 20)->default('draft');

            /* What it applies to. Empty means "not limited by this", which is
               why they are nullable rather than defaulting to an empty array:
               a form limited to no services at all and a form limited by
               nothing are different answers. */
            $table->json('service_ids')->nullable();
            $table->json('service_category_ids')->nullable();
            $table->json('location_ids')->nullable();

            /* How often it has to be done again, from config('forms.validity')
               — once, every appointment, or a period. `validity_days` carries
               the number for the custom option and is null for every other. */
            $table->string('validity', 20)->default('once');
            $table->unsignedSmallInteger('validity_days')->nullable();

            /* Whether the client signs it, which is what separates a waiver
               from a questionnaire and decides whether a submission may ever
               be edited afterwards. */
            $table->boolean('signature_required')->default(false);

            /* Whether StyleDesk mails or texts the link without being asked.
               The timing and the channel are the automation slice's; this is
               the switch the forms list reports on. */
            $table->boolean('auto_send')->default(false);

            /*
             * Medical history, medications, pregnancy status.
             *
             * Turning this on makes a form's answers encrypted at rest and
             * puts them behind their own permission. It is recorded per form
             * rather than assumed for medical types, because a business's own
             * "Custom" form can hold exactly the same thing and a type is a
             * label rather than a promise.
             */
            $table->boolean('contains_sensitive')->default(false);

            /* All questions on one screen, or one at a time. A presentation
               choice, so it lives with the form rather than the version. */
            $table->string('layout', 20)->default('classic');

            /* The version a new assignment is pinned to. Nullable only for
               the instant between creating the row and creating its first
               version — both happen in one transaction. */
            $table->unsignedBigInteger('current_version_id')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            /* Out of the way without being gone. Archiving a form with
               submissions against it must never destroy them: those are what
               a client signed. */
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
