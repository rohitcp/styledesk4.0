<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a shift has been communicated to the person working it.
 *
 * Kept apart from `status`, which says where the shift itself has got to —
 * scheduled, confirmed, cancelled. A cancelled shift can still be one the
 * staff member was told about, and a scheduled one can be a draft nobody has
 * seen; collapsing the two would lose whichever half was asked for second.
 *
 * `published_at` survives a shift going back to draft on purpose: an edit to a
 * published week is a change pending re-publication, not a week that was never
 * sent, and the page needs to tell those apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->string('publish_status', 20)->default('draft')->after('status');
            $table->timestamp('published_at')->nullable()->after('publish_status');

            /* The schedule page asks "what is the state of this range for this
               person" on every load, which is exactly this triple. */
            $table->index(['staff_id', 'date', 'publish_status'], 'staff_shifts_publish_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->dropIndex('staff_shifts_publish_state_index');
            $table->dropColumn(['publish_status', 'published_at']);
        });
    }
};
