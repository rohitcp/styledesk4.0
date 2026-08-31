<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Internal notes about a member of staff.
         *
         * The same shape as client_notes, deliberately: it is the same idea —
         * somebody in the business writing something down about somebody the
         * business deals with — and a second shape would mean a second editor,
         * a second permission rule and a second thing to fix.
         *
         * Internal in the strict sense: never shown to a client or on the
         * public booking pages, and reached only through the staff screens,
         * which are already behind the staff permissions.
         */
        Schema::create('staff_notes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            /**
             * Who wrote it. Nullable and nullOnDelete: a note outlives the
             * account that wrote it, and losing the author must not lose the
             * note — the history is the point.
             */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            $table->timestamps();

            $table->index(['staff_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notes');
    }
};
