<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the client has said yes.
 *
 * Its own column and not the booking's status, because they answer different
 * questions. `status` is what the salon has decided about the appointment —
 * confirmed, arrived, cancelled — and this is whether the person coming has
 * acknowledged it. A booking can be confirmed by the desk and unanswered by
 * the client, which is exactly the list a receptionist wants to ring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            /* not_requested, pending, confirmed, cancellation_requested,
               needs_review — see config/sms.php. */
            $table->string('client_confirmation', 30)->default('not_requested')->after('confirmation');
            $table->timestamp('client_confirmed_at')->nullable()->after('client_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['client_confirmation', 'client_confirmed_at']);
        });
    }
};
