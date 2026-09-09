<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The services a template names, when it names any.
 *
 * Empty is the normal case and means "whatever this booking is for" — a
 * confirmation should describe the appointment it was sent about, not a fixed
 * list. A template that names services is the other kind: aftercare for a
 * particular treatment, preparation instructions for one service.
 *
 * Ids rather than names, so a service renamed in the catalogue is renamed in
 * the email too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->json('detail_services')->nullable()->after('detail_fields');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('detail_services');
        });
    }
};
