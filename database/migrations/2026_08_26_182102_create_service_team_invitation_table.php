<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services an invited person will provide once they accept.
     *
     * Held against the invitation rather than written to a staff row up front,
     * because the staff row does not exist yet — someone who never accepts
     * must leave nothing behind that a booking could point at. On acceptance
     * these are copied into service_staff, which this table mirrors.
     */
    public function up(): void
    {
        Schema::create('service_team_invitation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_invitation_id')->constrained()->cascadeOnDelete();

            $table->unique(['service_id', 'team_invitation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_team_invitation');
    }
};
