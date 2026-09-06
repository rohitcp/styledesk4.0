<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email campaigns, and who each one went to.
 *
 * Two tables, because they answer two different questions. `email_campaigns`
 * is what somebody wrote and when they sent it; `email_campaign_recipients`
 * is what happened to each copy — delivered, opened, clicked, bounced — and
 * is the only place a per-client answer can live.
 *
 * The audience is stored as RULES rather than as a list of client ids. A
 * campaign that says "clients with no visit in 90 days" and is sent a week
 * later should go to who qualifies on the day it sends, not to who qualified
 * on the day it was drafted. The recipient rows are written when the send
 * begins, which is the moment the rules become a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();

            $table->string('name');
            $table->string('subject')->nullable();
            $table->string('preview_text')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();

            /* draft · scheduled · sending · sent · paused · cancelled · failed */
            $table->string('status', 20)->default('draft')->index();

            /* Who it goes to, as rules. See the class note above. */
            $table->json('audience')->nullable();
            /* What was written, block by block. Null until the design step. */
            $table->json('content')->nullable();
            $table->string('template')->nullable();

            /* What the audience came to when it was last counted, so the
               listing can say "about 1,248" without recounting every row on
               every page load. A cache, never the truth. */
            $table->unsignedInteger('estimated_recipients')->nullable();
            $table->timestamp('estimated_at')->nullable();

            $table->timestamp('scheduled_for')->nullable()->index();
            $table->string('timezone')->nullable();
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            /* The tallies, kept on the campaign so a report does not have to
               count a hundred thousand recipient rows to draw six numbers.
               Written from the recipient rows, never instead of them. */
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);

            /* What the provider calls it, so its events can be matched back. */
            $table->string('provider')->nullable();
            $table->string('provider_campaign_id')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            /* The address as it was on the day. A client who changes their
               email next month has not changed where this went. */
            $table->string('email');

            /* pending · sent · delivered · bounced · failed · unsubscribed */
            $table->string('status', 20)->default('pending')->index();
            $table->string('bounce_type', 20)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->unsignedSmallInteger('open_count')->default(0);
            $table->unsignedSmallInteger('click_count')->default(0);

            $table->timestamps();

            /* One row per client per campaign. The provider can report the
               same event twice and must not create a second recipient. */
            $table->unique(['email_campaign_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_campaigns');
    }
};
