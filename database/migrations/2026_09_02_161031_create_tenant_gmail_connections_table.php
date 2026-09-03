<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's connected Gmail or Google Workspace mailbox.
 *
 * One row per tenant, replaced rather than added to: a business sends as one
 * address, and two connections would be two answers to "who did that come
 * from".
 *
 * Its own table rather than columns on `tenants` because of what it holds. The
 * refresh token is a standing key to somebody's mailbox — the most dangerous
 * thing this application stores — and it belongs somewhere a careless
 * `Tenant::all()` does not drag it into memory, and somewhere a future audit
 * can look at on its own.
 *
 * Both tokens are encrypted at rest by the model's casts. The database is not
 * the only place they could leak from, but it is the one we control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_gmail_connections', function (Blueprint $table) {
            $table->id();

            /* Unique: one mailbox per business. Reconnecting overwrites rather
               than accumulating, so there is never a stale token that still
               works sitting behind the live one. */
            $table->string('tenant_id')->unique();

            /* The address Google says this is, read back from the token rather
               than typed by the person connecting. Somebody who mistypes their
               own address should not end up with mail going out as it. */
            $table->string('email');
            $table->string('google_name')->nullable();

            $table->text('access_token');
            /* Null when Google declines to issue one — which it does on a
               re-consent that reuses an existing grant. A connection without
               it works until the access token lapses and then cannot be
               renewed, which is exactly what "Needs Attention" is for. */
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->string('scopes', 500)->nullable();

            /* connected | needs_attention. A disconnected business has no row:
               a third state stored here would be a row describing its own
               absence. */
            $table->string('status', 20)->default('connected');
            $table->string('last_error')->nullable();

            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_gmail_connections');
    }
};
