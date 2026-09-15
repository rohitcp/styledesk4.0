<?php

declare(strict_types=1);

use App\Models\Form;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The form's own address.
 *
 * A link the business can hand out — put on a booking confirmation, texted to
 * a client, opened on a tablet at the desk — as opposed to the per-client
 * links `form_submissions.token` already carries. Both exist because they
 * answer different questions: that one is "this client's copy of this form",
 * this one is "the form".
 *
 * Random rather than derived from the name. A public form is reachable by
 * anyone holding the URL, so a guessable one is a form somebody can find
 * without being sent it — and a name-derived slug would also change under a
 * business that renames its form, breaking every link already sent.
 *
 * Written when the form is first published: a draft has no address because
 * there is nothing at the other end of it yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->unique()->after('status');
        });

        /*
         * Forms already published get theirs now.
         *
         * Without this a form that is live today would read "the link is
         * created when you publish the form" on its own settings screen —
         * which is both wrong and unfixable, because it is already published
         * and nothing would mint one.
         */
        Form::withoutGlobalScopes()
            ->whereNull('public_token')
            ->whereHas('versions', fn ($query) => $query->whereNotNull('published_at'))
            ->cursor()
            ->each(fn (Form $form) => $form->forceFill(['public_token' => Form::newPublicToken()])->save());
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
