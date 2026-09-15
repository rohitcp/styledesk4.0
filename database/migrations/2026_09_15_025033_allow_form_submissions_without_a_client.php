<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A submission that arrived before anybody knew whose it was.
 *
 * The form's public link is not a client's link: a business puts it on a
 * booking confirmation or its own website, and whoever opens it is a stranger
 * until they say otherwise. Requiring a client to save one would mean either
 * refusing the submission or inventing a client from a name typed into a box,
 * and a duplicate client record is worse than an unattached form.
 *
 * So the column is nullable and staff attach the submission to a client
 * afterwards. Client field mapping — matching an answer to an existing client
 * automatically — is the piece that will make that automatic; until it
 * exists, the honest state is "not yet attached".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable(false)->change();
        });
    }
};
