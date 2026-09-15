<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a form asked, at one point in its life.
 *
 * The questions live here and not on `forms` because a completed form is
 * evidence. A client signed a consent that said something specific; a
 * business that later rewords a question must not retroactively change what
 * anybody agreed to, and a business that reads an old submission must see the
 * questions it was actually answering rather than today's.
 *
 * So every submission pins the version it was assigned against, and editing a
 * form that has submissions produces a new row here rather than overwriting
 * this one. A form with no submissions yet is edited in place — a business
 * still building its first intake form should not accumulate nine versions
 * before anybody has seen it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            /* 1, 2, 3 — what the business sees on a submission and in version
               history, rather than this row's id. */
            $table->unsignedSmallInteger('version');

            /*
             * The questions, their order, their settings and their logic.
             *
             * One json document rather than a table of fields and a table of
             * options: a version is written whole by the builder and read
             * whole by the renderer, and nothing ever queries for "every form
             * with a pregnancy question". Storing it as rows would buy a
             * query nobody makes and cost the exactness that is the whole
             * point of a version.
             *
             * Null until the builder saves for the first time, which is the
             * honest state of a form created a moment ago from a name.
             */
            $table->json('schema')->nullable();

            /* Published is what makes a version assignable. A draft version
               of a live form is how an edit is prepared without changing what
               clients are being sent today. */
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            /* Where this version came from, so history reads as a chain
               rather than a set. Null on the first. */
            $table->foreignId('previous_version_id')->nullable()
                ->constrained('form_versions')->nullOnDelete();

            $table->timestamps();

            $table->unique(['form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');
    }
};
