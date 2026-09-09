<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The coupon an email offers, if it offers one.
 *
 * A reference rather than a copy of the code: a coupon that is disabled or has
 * expired must stop appearing in email, and a code written into the template
 * would keep going out long after the campaign ended. The block reads the
 * promotion at send time and draws nothing when it is no longer usable.
 *
 * Nulled rather than cascaded, so deleting a campaign does not delete the
 * email that mentioned it — the template simply stops showing a coupon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('cta_action')
                ->constrained('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });
    }
};
