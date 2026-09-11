<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages that come the other way.
 *
 * One shared number carries every business's texts, so a reply arrives
 * identifying nobody: a phone number, some words, and no clue which salon it
 * is about. Working that out is guesswork with a good success rate, and the
 * columns here are what lets StyleDesk admit when it has guessed wrong.
 *
 * `tenant_id` becomes nullable for the same reason. A reply that cannot be
 * matched to a business belongs to no business — and parking it against a
 * guess would show one salon another salon's client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('tenant_id');

            /* What the reply was taken to mean, and whether anybody should
               look at it. Null on outbound, which has nothing to interpret. */
            $table->string('reply_keyword', 20)->nullable()->after('body');
            $table->string('reply_status', 20)->nullable()->after('reply_keyword');
            /* Which businesses it could have been, when it could have been
               more than one. Read by whoever picks it up. */
            $table->json('reply_candidates')->nullable()->after('reply_status');
            $table->timestamp('handled_at')->nullable()->after('failed_at');
            $table->foreignId('handled_by')->nullable()->after('handled_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['direction', 'reply_status'], 'sms_messages_direction_reply_index');
            /* The reply matcher's own query: recent outbound to this number. */
            $table->index(['to_number', 'created_at'], 'sms_messages_to_created_index');
        });

        /* Nullable, for a reply nobody can place. Dropped and re-added rather
           than changed in place, because the column carries a foreign key and
           doctrine/dbal is not installed to alter one. */
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropIndex('sms_messages_direction_reply_index');
            $table->dropIndex('sms_messages_to_created_index');
            $table->dropColumn([
                'direction', 'reply_keyword', 'reply_status',
                'reply_candidates', 'handled_at', 'handled_by',
            ]);
        });
    }
};
