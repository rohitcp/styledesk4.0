<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the Locations settings module needs beyond what onboarding wrote.
 *
 * Onboarding creates one primary location with an address and opening hours,
 * because that is all a business needs to take its first booking. This adds
 * the fields a business with more than one branch needs: a code to tell them
 * apart, a manager, its own contact details, and a status that can retire a
 * branch without erasing what happened there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            /**
             * Guarded per column, not per migration.
             *
             * This migration touches three tables and a partial failure on the
             * third leaves the first two applied with nothing recorded in the
             * migrations table — the state this ran into once already. Column
             * checks make a re-run finish the job rather than fail on work it
             * has already done.
             */
            if (Schema::hasColumn('locations', 'code')) {
                return;
            }

            // ------------------------------------------------ identification
            $table->string('code', 20)->nullable()->after('name');
            $table->string('type', 20)->nullable()->after('code');

            /**
             * Active or inactive, not a boolean.
             *
             * §12 gives inactive a meaning a flag cannot carry on its own —
             * bookings stop, history stays — and a string leaves room for the
             * third state ("archived", "temporarily closed") this will grow
             * without another migration renaming a column called `is_active`.
             */
            $table->string('status', 12)->default('active')->after('is_primary');

            // ------------------------------------------------------- address
            $table->string('suite', 60)->nullable()->after('address_line2');

            // ------------------------------------------------------ managers
            /**
             * nullOnDelete, not cascade.
             *
             * A manager leaving the business must not take the branch with
             * them. The location loses its manager and says so; it does not
             * disappear along with every booking that points at it.
             */
            $table->foreignId('manager_staff_id')->nullable()->after('timezone')
                ->constrained('staff')->nullOnDelete();

            // ------------------------------------------------------- contact
            $table->string('phone_secondary', 32)->nullable()->after('phone_country');
            $table->string('email')->nullable()->after('phone_secondary');
            $table->string('booking_email')->nullable()->after('email');
            $table->string('support_email')->nullable()->after('booking_email');
            $table->string('website')->nullable()->after('support_email');
            $table->string('extension', 20)->nullable()->after('website');
            $table->string('contact_person', 120)->nullable()->after('extension');

            /**
             * Primary now defaults to false.
             *
             * It defaulted to true when a tenant could only have one location
             * and that location was necessarily the primary one. With a module
             * that adds branches, the same default means every new branch
             * silently claims to be the head office.
             */
            $table->boolean('is_primary')->default(false)->change();
        });

        /**
         * Split hours: more than one opening period on the same day.
         *
         * A row per period rather than a second pair of columns, because
         * "morning and afternoon" is not the limit — a business that closes
         * twice would need a third pair, and then a fourth. sort_order is what
         * keeps 9–1 before 2–7 when both are read back.
         */
        if (! Schema::hasColumn('location_hours', 'sort_order')) {
            Schema::table('location_hours', function (Blueprint $table) {
                $table->unsignedTinyInteger('sort_order')->default(0)->after('day_of_week');
            });
        }

        /**
         * The foreign key is given its own index before the unique one goes.
         *
         * MySQL was satisfying the location_id foreign key with the leading
         * column of the unique index and refused to drop it — error 1553 —
         * which is a fair complaint: dropping it would leave the constraint
         * with nothing to enforce itself against.
         */
        Schema::table('location_hours', function (Blueprint $table) {
            $table->index('location_id', 'location_hours_location_id_index');
        });

        Schema::table('location_hours', function (Blueprint $table) {
            // The old constraint is exactly what forbids a second period, so
            // it is replaced rather than added to.
            $table->dropUnique(['location_id', 'day_of_week']);
        });

        Schema::table('location_hours', function (Blueprint $table) {
            $table->unique(['location_id', 'day_of_week', 'sort_order']);
        });

        /**
         * Assistant managers, of which there may be several.
         *
         * A pivot rather than a second column on locations: §3 says multiple,
         * and assistant_manager_2_staff_id is the shape that has to be
         * migrated again the first time a branch needs three.
         */
        Schema::hasTable('location_assistant_manager') || Schema::create('location_assistant_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['location_id', 'staff_id']);
        });

        /**
         * The primary location, if one was never chosen.
         *
         * Onboarding sets is_primary on the row it creates, but a tenant whose
         * location predates that, or was made by a seeder, can have none — and
         * §1 says one location is normally primary. Backfilling the oldest is
         * the only answer that does not require asking.
         */
        DB::table('locations')
            ->whereNotIn('tenant_id', function ($query) {
                $query->select('tenant_id')->from('locations')->where('is_primary', true);
            })
            ->orderBy('id')
            ->get()
            ->groupBy('tenant_id')
            ->each(fn ($rows) => DB::table('locations')->where('id', $rows->first()->id)->update(['is_primary' => true]));
    }

    public function down(): void
    {
        Schema::dropIfExists('location_assistant_manager');

        // The extra periods cannot survive a schema that allows one row per
        // day, so they are dropped deliberately here rather than left to
        // break the unique index being restored below.
        DB::table('location_hours')->where('sort_order', '>', 0)->delete();

        Schema::table('location_hours', function (Blueprint $table) {
            $table->dropUnique(['location_id', 'day_of_week', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('location_hours', function (Blueprint $table) {
            $table->unique(['location_id', 'day_of_week']);
        });

        // Restored last, once the unique index is back to satisfy the foreign
        // key on its own.
        Schema::table('location_hours', function (Blueprint $table) {
            $table->dropIndex('location_hours_location_id_index');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_staff_id');
            $table->dropColumn([
                'code', 'type', 'status', 'suite', 'phone_secondary', 'email',
                'booking_email', 'support_email', 'website', 'extension', 'contact_person',
            ]);
        });
    }
};
