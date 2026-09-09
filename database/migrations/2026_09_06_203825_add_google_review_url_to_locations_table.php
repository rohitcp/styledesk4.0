<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a happy client is sent to say so publicly.
 *
 * On the location rather than the tenant: a business with three branches has
 * three Google listings, and pointing everybody at the head office's would
 * pile a suburb's reviews onto a high street. A branch with no URL simply does
 * not offer the button.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('google_review_url')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('google_review_url');
        });
    }
};
