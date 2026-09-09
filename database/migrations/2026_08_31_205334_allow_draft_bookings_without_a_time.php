<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booking saved before it has a time.
 *
 * The booking screen writes a draft as soon as it knows who the appointment
 * is for, which is well before anybody has said when — a receptionist takes
 * the name first and the diary second. Storing midnight for those would put a
 * 12:00 AM appointment in every listing that reads them, so the two columns
 * say nothing until there is something to say.
 *
 * Only a draft is ever without one: taking the booking works the times out
 * from the services, and `store()` has always required both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('starts_at')->nullable()->change();
            $table->time('ends_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('starts_at')->nullable(false)->change();
            $table->time('ends_at')->nullable(false)->change();
        });
    }
};
