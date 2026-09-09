<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own wording for one email.
 *
 * A row here is an override, not the template itself. StyleDesk ships a
 * default for every key in config/email_templates.php, and a business that
 * has never opened this screen has no rows at all and still sends properly
 * formatted mail — which is what stops a configuration gap from breaking
 * client communication.
 *
 * "Reset to StyleDesk Default" is therefore a delete, and cannot fail: there
 * is no stored copy of the default to restore from, because the default is in
 * the code.
 *
 * What is stored is content and choices — never HTML. The shell, the spacing,
 * the button styling and the responsive behaviour belong to StyleDesk, so a
 * salon owner cannot ship a broken email and StyleDesk can fix every email at
 * once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            /* The key is what everything else points at: a trigger for a
               transactional template, a name for a standard one. Unique per
               business, so "which template answers booking.confirmed" has one
               answer. */
            $table->string('key', 80);
            $table->string('type', 20);

            /* Null for standard templates, which nothing fires. */
            $table->string('trigger', 80)->nullable();

            $table->string('name');
            $table->string('subject');
            $table->string('heading')->nullable();

            /* The prose, as the owner typed it. Two fields rather than one
               because the shell places them differently: the intro sits above
               the details card and the supporting message below the button. */
            $table->text('intro')->nullable();
            $table->text('supporting_message')->nullable();

            /* Which blocks appear, and which rows the details card shows.
               Shapes live in config; these are the overrides. */
            $table->json('blocks')->nullable();
            $table->json('detail_fields')->nullable();

            $table->boolean('cta_enabled')->default(true);
            $table->string('cta_label')->nullable();
            $table->string('cta_action', 40)->nullable();

            /* Disabled, never deleted. A transactional template that is off
               simply does not send, and turning it back on restores the
               wording the business wrote. */
            $table->boolean('is_active')->default(true);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'trigger']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
