<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reset tokens for the platform console, in a table of their own.
 *
 * Laravel's token repository keys on the email address and nothing else —
 * there is no guard column and no discriminator. Pointing both brokers at
 * `password_reset_tokens` therefore means one row per address across both
 * consoles: a salon owner and an administrator who share an address share a
 * token, and the link mailed for one is redeemable against the other. The
 * separation the console is built on would end at the reset form.
 *
 * A second table is the whole fix, and it costs one migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_password_reset_tokens');
    }
};
