<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Smile Spa — a massage and wellness centre to develop against.
 *
 *   php artisan db:seed --class=SmileSpaSeeder
 *
 * Ten rooms and chairs, ten service categories and about fifty services, all
 * of it hung together the way the booking engine reads it rather than the way
 * a price list is written.
 *
 * The one idea worth understanding before changing anything here is how a
 * service says what it needs. It does not name a room. It is attached to
 * every resource that could do the work — six single rooms and, behind them,
 * the two couple rooms — and the engine picks from what is free. That is what
 * makes "a couple room may take a single massage when no single room is
 * spare" true without a line of code anywhere saying so, and what lets a
 * seventh room be added next spring without touching a service.
 *
 * Order is expressed as `position`, lowest first: single rooms 1–6, then the
 * couple rooms at 10 and 11, then the chairs. A single massage should land in
 * a single room while one is free and fall back to a couple room only when
 * none is — see the note on the couple rooms below for how far that is
 * currently enforced.
 *
 * Idempotent. Run it twice and nothing is duplicated: everything is matched
 * on the code or the name a person would recognise it by.
 */
class SmileSpaSeeder extends Seeder
{
    /** The account this business belongs to. */
    private const OWNER_EMAIL = 'emma.martin3@example.com';

    private const CURRENCY = 'USD';

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->first();

        if ($owner?->tenant === null) {
            $this->command?->error('No business found for '.self::OWNER_EMAIL.'.');

            return;
        }

        $tenant = $owner->tenant;
        $tenant->forceFill(['name' => 'Smile Spa', 'currency_code' => self::CURRENCY])->save();

        $location = $this->location($tenant);
        $resources = $this->resources($tenant, $location);
        $categories = $this->serviceCategories($tenant);

        $this->services($tenant, $categories, $resources);
        $this->team($tenant, $location);

        $this->command?->info('Seeded Smile Spa — '.$resources->count().' resources, '
            .Service::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey())->count().' services, '
            .Staff::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey())->count().' staff.');
    }

    /** 336A Main Street, Hackensack. */
    private function location(Tenant $tenant): Location
    {
        $location = Location::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('is_primary', true)
            ->first();

        $attributes = [
            'name' => 'Main Location',
            'address_line1' => '336A Main Street',
            'city' => 'Hackensack',
            'state' => 'NJ',
            'postal_code' => '07601',
            'country' => 'US',
            'timezone' => 'America/New_York',
            'is_primary' => true,
            'status' => 'active',
        ];

        if ($location === null) {
            return Location::withoutGlobalScopes()->create(
                $attributes + ['tenant_id' => $tenant->getTenantKey()]
            );
        }

        $location->forceFill($attributes)->save();

        return $location;
    }

    /**
     * Ten bookable things: two chairs, six single rooms, two couple rooms.
     *
     * @return Collection<string, resource> keyed by code
     */
    private function resources(Tenant $tenant, Location $location): Collection
    {
        $chairs = $this->resourceCategory($tenant, 'massage-chair', 'Massage chair', 'chairs', 1);
        $rooms = $this->resourceCategory($tenant, 'massage-room', 'Massage room', 'rooms', 1);
        $couples = $this->resourceCategory($tenant, 'couples-massage-room', 'Couples massage room', 'rooms', 2);

        $rows = [
            /* Seated work: reflexology, head, hand, a short neck and shoulder. */
            ['CHAIR-01', 'Chair 01', $chairs, 1, 20],
            ['CHAIR-02', 'Chair 02', $chairs, 1, 21],
        ];

        /* Positions 1–6, so a single massage is offered a single room before
           either couple room is considered. */
        foreach (range(1, 6) as $number) {
            $code = sprintf('ROOM-S%02d', $number);
            $rows[] = [$code, sprintf('Single Room %02d', $number), $rooms, 1, $number];
        }

        /*
         * Two beds, and both of them go together.
         *
         * A couple booking takes the room, not a bed: there is no second
         * appointment to be squeezed into the other half, because the second
         * bed is where the other client is lying. Capacity 2 says how many
         * people the room holds, not how many bookings it can carry.
         *
         * They sit behind the single rooms at 10 and 11 so they are the last
         * thing a single massage is given.
         */
        $rows[] = ['ROOM-C01', 'Couple Room 01', $couples, 2, 10];
        $rows[] = ['ROOM-C02', 'Couple Room 02', $couples, 2, 11];

        return collect($rows)->mapWithKeys(function (array $row) use ($tenant, $location) {
            [$code, $name, $category, $capacity, $position] = $row;

            $resource = Resource::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'code' => $code],
                [
                    'name' => $name,
                    'resource_category_id' => $category->id,
                    'location_id' => $location->id,
                    'capacity' => $capacity,
                    'position' => $position,
                    'is_active' => true,
                    'availability_status' => 'available',
                    'availability_type' => 'location',
                ]
            );

            return [$code => $resource];
        });
    }

    /** A category, whether StyleDesk supplied it or this business needs it. */
    private function resourceCategory(Tenant $tenant, string $key, string $name, string $group, int $capacity): ResourceCategory
    {
        $existing = ResourceCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            /* Switched on, in case the business had turned it off. Its name
               is left alone: renaming a category is theirs to do. */
            $existing->forceFill(['is_active' => true])->save();

            return $existing;
        }

        return ResourceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'key' => $key,
            'name' => $name,
            'group' => $group,
            'default_capacity' => $capacity,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * The ten headings the price list is read under.
     *
     * @return Collection<string, ServiceCategory>
     */
    private function serviceCategories(Tenant $tenant): Collection
    {
        $names = [
            'Body Massage',
            'Couple Massage',
            'Therapeutic Massage',
            'Lymphatic & Abdominal Treatments',
            'Aromatherapy & Hot Stone',
            'Head / Neck / Shoulder Massage',
            'Foot & Reflexology',
            'Specialty Massage',
            'Combination Treatments',
            'Facial & Body Treatments',
            'Add-On Treatments',
        ];

        return collect($names)->mapWithKeys(fn (string $name, int $index) => [
            $name => ServiceCategory::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'name' => $name],
                [
                    'status' => ServiceCategory::STATUS_ACTIVE,
                    'display_order' => $index,
                    'is_system' => false,
                ]
            ),
        ]);
    }

    /**
     * Everything on the menu, and what each of them needs to be done in.
     *
     * The last value on each row is which pool of resources can carry it —
     * see `pool()` for what each name means. It is not a room number, and
     * that is the point.
     *
     * @param  Collection<string, ServiceCategory>  $categories
     * @param  Collection<string, resource>  $resources
     */
    private function services(Tenant $tenant, Collection $categories, Collection $resources): void
    {
        $menu = [
            'Body Massage' => [
                ['Deep Tissue Massage — 60 Minutes', 60, 6500, 'room'],
                ['Deep Tissue Massage — 90 Minutes', 90, 9000, 'room'],
                ['Deep Tissue Massage — 120 Minutes', 120, 13000, 'room'],
                ['Swedish Massage — 60 Minutes', 60, 6500, 'room'],
                ['Swedish Massage — 90 Minutes', 90, 9000, 'room'],
                ['Swedish Massage — 120 Minutes', 120, 13000, 'room'],
                ['Body Massage — 70 Minutes', 70, 7300, 'room'],
                ['Back Massage — 30 Minutes', 30, 4000, 'room'],
            ],

            /* Two clients, two therapists, one room, both beds. The room is
               the whole booking, which is why these are the only services
               that cannot fall back to a single room. */
            'Couple Massage' => [
                ['Couple Massage — 60 Minutes', 60, 13000, 'couple'],
                ['Couple Massage — 80 Minutes', 80, 16600, 'couple'],
                ['Couple Massage — 90 Minutes', 90, 18000, 'couple'],
            ],

            'Therapeutic Massage' => [
                ['Sciatica & Body Massage', 60, 8000, 'room'],
                ['Thai Massage — 30 Minutes', 30, 5500, 'room'],
                ['Thai Massage — 60 Minutes', 60, 9000, 'room'],
                ['Thai Massage — 90 Minutes', 90, 13000, 'room'],
                ['Sports Massage — 60 Minutes', 60, 7000, 'room'],
                ['Shiatsu Massage', 60, 10000, 'room'],
                ['Prenatal Massage — 60 Minutes', 60, 7000, 'room'],
            ],

            'Lymphatic & Abdominal Treatments' => [
                ['Lymphatic Drainage Massage — Premium', 60, 13000, 'room'],
                ['Lymphatic Drainage Massage', 60, 9500, 'room'],
                ['Abdominal Massage — 30 Minutes', 30, 6000, 'room'],
                ['Abdominal Massage — 60 Minutes', 60, 10500, 'room'],
            ],

            'Aromatherapy & Hot Stone' => [
                ['Aromatherapy Massage — 60 Minutes', 60, 7500, 'room'],
                ['Aromatherapy Massage — 90 Minutes', 90, 10500, 'room'],
                ['Aromatherapy Massage — 120 Minutes', 120, 14000, 'room'],
                ['Hot Stone Body Massage — 60 Minutes', 60, 8000, 'room'],
                ['Aroma Body + Aroma Head Massage', 70, 8500, 'room', '40 minutes aroma body massage, then 30 minutes aroma head massage.'],
                ['Hot Stone + Aroma Head Massage', 70, 8500, 'room', '40 minutes hot stone massage, then 30 minutes aroma head massage.'],
            ],

            /* Seated where it can be, lying down where the client would
               rather. The chair is first in the list and the rooms are behind
               it, which is the whole of the fallback. */
            'Head / Neck / Shoulder Massage' => [
                ['Shoulder & Neck Massage — 30 Minutes', 30, 5500, 'chair'],
                ['Shoulder & Neck Massage — 60 Minutes', 60, 8000, 'room'],
                ['Neck & Shoulder + Head Massage', 45, 6500, 'chair', '25 minutes neck and shoulder, then 20 minutes head massage.'],
                ['Head & Scalp Massage', 20, 2500, 'chair'],
                ['Aroma Head & Scalp Massage', 30, 5000, 'chair', 'Aroma head massage, scalp massage and a warm eye mask.'],
            ],

            'Foot & Reflexology' => [
                ['Foot Reflexology — 30 Minutes', 30, 3700, 'chair'],
                ['Foot Reflexology — 60 Minutes', 60, 6000, 'chair'],
                ['Foot Reflexology — 90 Minutes', 90, 8500, 'chair'],
                ['Aroma Head & Foot Massage', 60, 6500, 'chair'],
                ['Hand Massage', 20, 2500, 'chair'],
            ],

            'Combination Treatments' => [
                ['Facial + Body Massage', 60, 7500, 'room', '20 minute facial, then a 40 minute body massage.'],
                ['Body + Abdominal Massage', 75, 12000, 'room', '45 minute body massage, then 30 minutes abdominal.'],
                ['Body + Neck & Shoulder Massage', 60, 8000, 'room', '40 minute body massage, then 20 minutes neck and shoulder.'],
                ['Body Massage + Facial', 120, 14500, 'room', '60 minute body massage, then a 60 minute facial.'],
            ],

            /* Fifteen minutes on its own or added to something longer, and
               either way it happens in whichever room the client is already
               in — so it is attached to the same pool as a body massage. */
            'Add-On Treatments' => [
                ['Cupping', 15, 2700, 'room', 'Fifteen minutes, on its own or added to another treatment in the same room.'],
            ],
        ];

        foreach ($menu as $categoryName => $rows) {
            foreach ($rows as $row) {
                [$name, $minutes, $priceMinor, $pool] = $row;

                $service = Service::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->getTenantKey(), 'name' => $name],
                    [
                        'service_category_id' => $categories[$categoryName]->id,
                        'duration_minutes' => $minutes,
                        'description' => $row[4] ?? null,
                        'is_active' => true,
                        'online_booking_enabled' => true,
                        /* Every treatment here needs somewhere to happen. */
                        'requires_resource' => true,
                        'taxable' => true,
                    ]
                );

                $service->prices()->updateOrCreate(
                    ['currency_code' => self::CURRENCY],
                    ['price_minor' => $priceMinor]
                );

                /* Attached to a pool rather than to a room. Adding a seventh
                   single room next spring means adding it to this pool, not
                   editing fifty services. */
                $service->resources()->sync($this->pool($pool, $resources));
            }
        }
    }

    /**
     * The people who do the work.
     *
     * Ten of them, and not ten of the same person: a spa is run by therapists
     * who each do some of the list rather than all of it, and a team where
     * everybody can do everything makes the booking screen's staff filter
     * meaningless — every name would come back for every service.
     *
     * So each is given the categories they actually work in. The two who
     * cover couple massage matter most: it takes two therapists at once, and
     * a team with only one of them could never carry a single couple booking.
     */
    private function team(Tenant $tenant, Location $location): void
    {
        $categories = ServiceCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->get()
            ->keyBy('name');

        $roles = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->get()
            ->keyBy('key');

        /* name, job title, role, what they work in.
           `*` is everything — the two senior therapists who can take any
           booking on the sheet, including a couple. */
        $team = [
            ['Mai', 'Nguyen', 'Senior Massage Therapist', 'manager', '*'],
            ['Ravi', 'Menon', 'Senior Massage Therapist', 'service-provider', '*'],
            ['Sofia', 'Alvarez', 'Massage Therapist', 'service-provider', ['Body Massage', 'Therapeutic Massage', 'Combination Treatments', 'Add-On Treatments']],
            ['Chen', 'Wei', 'Massage Therapist', 'service-provider', ['Body Massage', 'Therapeutic Massage', 'Lymphatic & Abdominal Treatments']],
            ['Aisha', 'Rahman', 'Aromatherapist', 'service-provider', ['Aromatherapy & Hot Stone', 'Body Massage', 'Combination Treatments']],
            ['Daniel', 'Okafor', 'Sports Therapist', 'service-provider', ['Therapeutic Massage', 'Body Massage']],
            ['Yuki', 'Tanaka', 'Reflexologist', 'service-provider', ['Foot & Reflexology', 'Head / Neck / Shoulder Massage']],
            ['Elena', 'Petrova', 'Reflexologist', 'service-provider', ['Foot & Reflexology', 'Head / Neck / Shoulder Massage', 'Add-On Treatments']],
            ['Marco', 'Rossi', 'Massage Therapist', 'service-provider', ['Body Massage', 'Aromatherapy & Hot Stone', 'Head / Neck / Shoulder Massage']],
            /* The desk. Books everybody in and does none of it — which is why
               `provides_services` is off rather than their service list being
               empty: an empty list reads as "not set up yet". */
            ['Grace', 'Bennett', 'Front Desk', 'front-desk', []],
        ];

        foreach ($team as [$first, $last, $title, $roleKey, $works]) {
            $provides = $roleKey !== 'front-desk';

            $staff = Staff::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getTenantKey(),
                    'email' => mb_strtolower($first.'.'.$last).'@smilespa.test',
                ],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'job_title' => $title,
                    'role' => $roleKey,
                    'role_id' => $roles[$roleKey]?->id,
                    'location_id' => $location->id,
                    'provider_type' => match ($roleKey) {
                        'manager' => 'manager-provider',
                        'front-desk' => 'front-desk',
                        default => 'service-provider',
                    },
                    'employment_type' => 'full-time',
                    'provides_services' => $provides,
                    'is_active' => true,
                ]
            );

            /* A staff number, but only for somebody who has not got one:
                re-running the seeder should not renumber the team. */
            if ($staff->employee_ref === null) {
                $staff->forceFill(['employee_ref' => Staff::nextRef($tenant->getTenantKey())])->save();
            }

            $staff->services()->sync($this->worksOn($tenant, $categories, $works));
        }
    }

    /**
     * Every service under the categories somebody works in.
     *
     * By category rather than by service, because the list will grow: a new
     * hot stone treatment next spring should be bookable with the
     * aromatherapist without anybody remembering to tick a box.
     *
     * @param  array<int, string>|string  $works
     * @return array<int, int>
     */
    private function worksOn(Tenant $tenant, Collection $categories, array|string $works): array
    {
        if ($works === []) {
            return [];
        }

        $services = Service::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey());

        if ($works !== '*') {
            $services->whereIn(
                'service_category_id',
                collect($works)->map(fn (string $name) => $categories[$name]->id)->all()
            );
        }

        return $services->pluck('id')->all();
    }

    /**
     * Which resources could carry a service, and in what order.
     *
     * Priority 1 is the preferred resource, 2 the fallback, 3 the last
     * resort. Equal numbers mean the business has no preference between
     * them, which is why the six single rooms all share one: nothing about
     * them differs, and numbering them 1 to 6 would claim otherwise.
     *
     * - `room`   single rooms at 1, couple rooms at 2. A single massage
     *            lands in a single room while one is free and takes a couple
     *            room only when none is.
     * - `couple` the couple rooms, both at 1. Two beds, and both of them go.
     * - `chair`  chairs at 1, single rooms at 2, couple rooms at 3 — for a
     *            client who would rather lie down, and only after everything
     *            else is taken.
     *
     * The order is written onto the pairing rather than onto the resource,
     * because the same room is a body massage's first choice and a
     * reflexology's second, and one number on the room could only be one.
     *
     * @return array<int, array<string, int>> resource id => pivot values
     */
    private function pool(string $pool, Collection $resources): array
    {
        $singles = ['ROOM-S01', 'ROOM-S02', 'ROOM-S03', 'ROOM-S04', 'ROOM-S05', 'ROOM-S06'];
        $couples = ['ROOM-C01', 'ROOM-C02'];
        $chairs = ['CHAIR-01', 'CHAIR-02'];

        $tiers = match ($pool) {
            'couple' => [1 => $couples],
            'chair' => [1 => $chairs, 2 => $singles, 3 => $couples],
            default => [1 => $singles, 2 => $couples],
        };

        $pivots = [];

        foreach ($tiers as $priority => $codes) {
            foreach ($codes as $code) {
                $pivots[$resources[$code]->id] = ['priority' => $priority];
            }
        }

        return $pivots;
    }
}
