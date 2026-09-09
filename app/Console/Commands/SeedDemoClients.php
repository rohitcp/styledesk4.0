<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\ClientTag;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo clients, for trying the listing against a realistic number of them.
 *
 * A development tool, not a seeder that runs with the others: it writes into
 * a real business's records, and nothing should do that as a side effect of
 * setting a machine up.
 *
 * Every client it creates carries the "Demo data" tag, which is what makes
 * the work reversible — --remove deletes exactly what this wrote and nothing
 * a person typed. Identifying them by anything else (a date range, an id
 * range, an email pattern) would eventually delete a real client.
 */
class SeedDemoClients extends Command
{
    protected $signature = 'styledesk:demo-clients
        {--tenant= : The business to add them to, by id or slug. Defaults to the only one, or asks.}
        {--count=500 : How many to create}
        {--remove : Delete the clients this command created, and the tag}';

    protected $description = 'Create demo client records for testing search, filters and the grid';

    /** The tag every demo client carries, and the only way they are found again. */
    private const TAG = 'Demo data';

    public function handle(): int
    {
        $tenant = $this->tenant();

        if (! $tenant) {
            return self::FAILURE;
        }

        return $this->option('remove')
            ? $this->remove($tenant)
            : $this->seed($tenant, max(1, (int) $this->option('count')));
    }

    private function seed(Tenant $tenant, int $count): int
    {
        $tag = ClientTag::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->getTenantKey(), 'label' => self::TAG],
            ['color' => 'slate', 'is_active' => true, 'position' => 99],
        );

        $settings = ClientSettings::forTenant($tenant);
        $statuses = [Client::STATUS_ACTIVE, Client::STATUS_ACTIVE, Client::STATUS_ACTIVE, Client::STATUS_INACTIVE, Client::STATUS_ARCHIVED];

        $locations = $tenant->locations()->pluck('id')->all();
        $staff = $tenant->staff()->pluck('id')->all();

        $this->components->info("Adding {$count} demo clients to {$tenant->name}.");
        $bar = $this->output->createProgressBar($count);

        /**
         * The reference is taken once and counted forward rather than asked
         * for per row: nextRef() locks the table to answer, and five hundred
         * of those is five hundred locks for a number this loop already knows.
         */
        $prefix = config('clients.client_id.prefix');
        $padding = config('clients.client_id.padding');

        // The digits after the prefix, not "every numeric character in the
        // string": CL-000002 sanitised the second way keeps its hyphen and
        // reads back as minus two.
        $next = (int) mb_substr(Client::nextRef($tenant->getTenantKey()), mb_strlen($prefix));

        DB::transaction(function () use ($tenant, $count, $tag, $statuses, $locations, $staff, $next, $prefix, $padding, $bar) {
            foreach (range(0, $count - 1) as $i) {
                $first = fake()->firstName();
                $last = fake()->lastName();

                /**
                 * Deliberately uneven: some clients have no email, some no
                 * mobile, some neither, and a few have two of each. A search
                 * box tested against five hundred complete records is only
                 * tested against the easy half.
                 */
                $hasEmail = fake()->boolean(85);
                $hasMobile = fake()->boolean(90);

                $email = $hasEmail
                    ? Str::lower($first.'.'.$last.$i.'@example.test')
                    : null;

                $mobile = $hasMobile
                    ? '+1 '.fake()->numberBetween(200, 989).'-555-'.str_pad((string) fake()->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT)
                    : null;

                $client = Client::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->getTenantKey(),
                    'client_ref' => $prefix.str_pad((string) ($next + $i), $padding, '0', STR_PAD_LEFT),
                    'first_name' => $first,
                    'last_name' => $last,
                    'mobile' => $mobile,
                    'email' => $email,
                    'status' => fake()->randomElement($statuses),
                    'date_of_birth' => fake()->boolean(60) ? fake()->dateTimeBetween('-70 years', '-18 years') : null,
                    'city' => fake()->boolean(40) ? fake()->city() : null,
                    'preferred_location_id' => $locations && fake()->boolean(70) ? fake()->randomElement($locations) : null,
                    'preferred_staff_id' => $staff && fake()->boolean(60) ? fake()->randomElement($staff) : null,

                    // Bookings do not exist yet, so these stay null and the
                    // grid says "No visits yet" — the same thing it says for
                    // every real client today.
                    'comm_email' => true,
                    'comm_sms' => fake()->boolean(80),
                    'comm_phone' => fake()->boolean(50),
                    'marketing_email' => fake()->boolean(35),
                    'marketing_sms' => fake()->boolean(20),
                ]);

                $client->tags()->attach($tag->id);

                if ($mobile) {
                    $client->syncPhones([
                        ['number' => $mobile, 'country' => 'US', 'type' => 'mobile', 'is_primary' => true],
                        ...(fake()->boolean(20) ? [[
                            'number' => '+1 '.fake()->numberBetween(200, 989).'-555-'.str_pad((string) fake()->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
                            'country' => 'US', 'type' => 'work', 'is_primary' => false,
                        ]] : []),
                    ]);
                }

                if ($email) {
                    $client->syncEmails([
                        ['email' => $email, 'type' => 'personal', 'is_primary' => true],
                        ...(fake()->boolean(15) ? [[
                            'email' => Str::lower($first.$i.'@work.example.test'), 'type' => 'work', 'is_primary' => false,
                        ]] : []),
                    ]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->components->info(sprintf(
            '%d demo clients added, all tagged "%s". Remove them with: php artisan styledesk:demo-clients --remove --tenant=%s',
            $count,
            self::TAG,
            $tenant->getTenantKey(),
        ));

        return self::SUCCESS;
    }

    private function remove(Tenant $tenant): int
    {
        $tag = ClientTag::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('label', self::TAG)
            ->first();

        if (! $tag) {
            $this->components->warn('No demo clients found on '.$tenant->name.'.');

            return self::SUCCESS;
        }

        $ids = DB::table('client_client_tag')->where('client_tag_id', $tag->id)->pluck('client_id');

        if (! $this->confirm(sprintf('Delete %d demo clients from %s?', $ids->count(), $tenant->name), true)) {
            return self::SUCCESS;
        }

        // The pivots, phones, emails and notes go with them: every one of
        // those tables cascades from the client.
        Client::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        $tag->delete();

        $this->components->info($ids->count().' demo clients removed.');

        return self::SUCCESS;
    }

    /** Which business to write to — never guessed when there is more than one. */
    private function tenant(): ?Tenant
    {
        $given = $this->option('tenant');

        if ($given) {
            $tenant = Tenant::where('id', $given)->orWhere('slug', $given)->first();

            if (! $tenant) {
                $this->components->error('No business matches "'.$given.'".');
            }

            return $tenant;
        }

        $tenants = Tenant::all();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $name = $this->choice('Which business?', $tenants->pluck('name')->all());

        return $tenants->firstWhere('name', $name);
    }
}
