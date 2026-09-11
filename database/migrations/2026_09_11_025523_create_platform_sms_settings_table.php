<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's own SMS arrangement.
 *
 * Not tenant-scoped, deliberately: there is one carrier account and one shared
 * number for the whole of StyleDesk, and which carrier that is belongs to the
 * people who pay the bill rather than to any one salon.
 *
 * One row. A settings table with many rows is a settings table where somebody
 * eventually reads the wrong one.
 *
 * The credentials are encrypted at rest. They can send messages at StyleDesk's
 * expense to any number in the world, and a database dump handed to a
 * contractor should not be a carrier account handed to a contractor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_sms_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('is_enabled')->default(false);
            /* clicksend, telnyx or disabled. Read only where the installation
               is allowed to reach a carrier at all — a developer's machine
               ignores it. See App\Messaging\SmsProviders. */
            $table->string('provider', 20)->default('disabled');

            /* Encrypted, so they are longer than they look. */
            $table->text('telnyx_key')->nullable();
            $table->text('telnyx_public_key')->nullable();
            $table->string('telnyx_from', 32)->nullable();

            $table->text('clicksend_username')->nullable();
            $table->text('clicksend_key')->nullable();
            $table->text('clicksend_webhook_secret')->nullable();
            $table->string('clicksend_from', 32)->nullable();

            /* Who last changed the carrier, and when. Switching provider
               changes where every business's messages go and what they cost;
               that is not a change anybody should be able to make anonymously. */
            $table->foreignId('updated_by')->nullable()
                ->constrained('backoffice_admins')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sms_settings');
    }
};
