<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the users table with the sign-up form in the prototype.
 *
 * signup.html asks for first and last name separately and requires an
 * explicit terms checkbox, which its own comment calls out as a spec
 * requirement (§2: explicit acceptance before account creation) rather than
 * the implicit consent-under-the-button the reference design used.
 *
 * `name` is dropped rather than kept alongside: a display column derived from
 * two others is a column that drifts the moment one of them is edited. The
 * User model exposes a `name` accessor instead, so anything reading ->name
 * keeps working and there is only ever one source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->after('tenant_id')->default('');
            $table->string('last_name', 100)->after('first_name')->default('');
            $table->timestamp('terms_accepted_at')->nullable()->after('password');
        });

        // Split any existing rows before the source column goes away.
        foreach (\DB::table('users')->select('id', 'name')->get() as $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);

            \DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('tenant_id')->default('');
        });

        foreach (\DB::table('users')->select('id', 'first_name', 'last_name')->get() as $user) {
            \DB::table('users')->where('id', $user->id)->update([
                'name' => trim($user->first_name.' '.$user->last_name),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'terms_accepted_at']);
        });
    }
};
