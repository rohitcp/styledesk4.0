<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // §4's optional personal details. One address field rather than
            // six: this is a home address kept for records, never geocoded or
            // matched on, so splitting it would be structure nothing uses.
            $table->text('address')->nullable()->after('secondary_phone');
            $table->string('emergency_contact_name', 120)->nullable()->after('address');
            $table->string('emergency_contact_phone', 32)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_phone');
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            /**
             * The staff record this invitation belongs to.
             *
             * §15 requires acceptance to link the existing staff record rather
             * than create a second one. Without this column the two are only
             * connected by email, which stops being true the moment someone is
             * invited at one address and accepts having changed it.
             */
            $table->foreignId('staff_id')->nullable()->after('tenant_id')->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'address', 'emergency_contact_name',
                'emergency_contact_phone', 'emergency_contact_relationship',
            ]);
        });
    }
};
