<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people who administer StyleDesk itself.
 *
 * A table of their own rather than a flag on `users`. A StyleDesk employee is
 * not a salon's staff member: they belong to no tenant, and every tenant-scoped
 * model in the application is filtered by a global scope that would have
 * nothing to filter them by. Keeping them apart also means the two sign-ins
 * cannot be confused for one another — a stolen salon session can never reach
 * the platform's own console, because it authenticates against a different
 * guard, a different table and a different set of cookies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            /* Which of the platform roles this administrator holds. A string
               rather than a foreign key: the roles are a fixed list in
               config/backoffice.php, deployed with the code, and a table of
               them would be a second place for the same answer to live. */
            $table->string('role')->index();

            /* Disabled rather than deleted. An administrator who has left the
               company still wrote the history their name is on, and a row
               removed would leave that history unattributed. */
            $table->string('status')->default('active')->index();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('backoffice_admins')->nullOnDelete();

            /* Read on the administrators screen, and the first thing anybody
               looks at when an account is suspected of being misused. */
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_admins');
    }
};
