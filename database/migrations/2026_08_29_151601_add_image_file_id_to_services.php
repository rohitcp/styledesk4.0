<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a service's images is the one clients see first.
 *
 * The images themselves are ordinary stored_files rows pointed at the service
 * — that is what StoredFile's entity_type/entity_id are for, and it means a
 * service's gallery is stored the same way as every other file in StyleDesk.
 * All this column adds is which one of them leads.
 *
 * nullOnDelete rather than cascade: losing the default image must leave the
 * service standing. The gallery then supplies the next one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('image_file_id')->nullable()->after('color')
                ->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_file_id');
        });
    }
};
