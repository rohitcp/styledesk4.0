<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give back the phone numbers and email addresses that were only ever cached.
 *
 * `clients.mobile` and `clients.email` are a cache of the primary contact —
 * see Client::writeContacts. The record is `client_phones` and
 * `client_emails`, and every screen that shows more than one number reads the
 * record.
 *
 * Anything that filled the columns without going through syncPhones() or
 * syncEmails() therefore produced a client whose number appeared in the
 * listing, which reads the cache, and nowhere on their profile, which reads
 * the record. This writes the missing rows from the cache.
 *
 * Only where the client has no rows of that kind at all. A client who already
 * has contacts has a record, and the cache is downstream of it — overwriting
 * from a cache would be the tail wagging the dog.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('clients')
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw(1)
                ->from('client_phones')
                ->whereColumn('client_phones.client_id', 'clients.id'))
            ->orderBy('id')
            ->chunkById(500, function ($clients) use ($now) {
                DB::table('client_phones')->insert(
                    collect($clients)->map(fn ($client) => [
                        'tenant_id' => $client->tenant_id,
                        'client_id' => $client->id,
                        'number' => $client->mobile,
                        'country' => null,
                        /* Mobile, because that is the column it came out of.
                           A guess at "home" or "work" would be inventing a
                           fact nobody recorded. */
                        'type' => 'mobile',
                        'is_primary' => true,
                        'position' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });

        DB::table('clients')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw(1)
                ->from('client_emails')
                ->whereColumn('client_emails.client_id', 'clients.id'))
            ->orderBy('id')
            ->chunkById(500, function ($clients) use ($now) {
                DB::table('client_emails')->insert(
                    collect($clients)->map(fn ($client) => [
                        'tenant_id' => $client->tenant_id,
                        'client_id' => $client->id,
                        'email' => $client->email,
                        'type' => 'personal',
                        'is_primary' => true,
                        'position' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    /**
     * Nothing.
     *
     * These rows are contact details for real people. Deleting them on a
     * rollback would take away the only copy — the cache they were written
     * from says one number, and by then the client may have several.
     */
    public function down(): void
    {
        //
    }
};
