<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every file StyleDesk stores, wherever it is stored.
 *
 * The row is the file as far as the rest of the app is concerned: features
 * hold an id, not a path. That is what lets the same code work against a
 * local directory in development and a DigitalOcean Space in production, and
 * what makes "does this file belong to this business" a question with an
 * answer rather than a guess made from a string.
 *
 * `storage_disk` is recorded per row rather than read from config at display
 * time, because a business that moves to Spaces still has yesterday's files
 * on disk — and a URL built from today's config for a file stored under
 * yesterday's would point at nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            /**
             * What the file belongs to, as a type and an id rather than a
             * foreign key: a file can hang off a client, a booking, a service
             * or something that does not exist yet, and one nullable column
             * per module would grow forever.
             */
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            /** What kind of file it is — client-file, logo, editor-attachment. */
            $table->string('category', 60);

            /* What the person called it, and what it is called on disk. Both,
               because the first is what a download should be named and the
               second is what cannot collide. */
            $table->string('original_filename');
            $table->string('stored_filename');

            $table->string('storage_disk', 40);
            $table->string('storage_path');

            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            /** public for a logo, private for anything about a client. */
            $table->string('visibility', 20)->default('private');

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /* Soft deletes: a file removed from a record still has to be
               accounted for, and a hard delete leaves an audit trail that
               says a file existed and nothing about what happened to it. */
            $table->softDeletes();

            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
