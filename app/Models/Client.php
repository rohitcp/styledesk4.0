<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ClientOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A person the business sees.
 *
 * What a client record carries is the business's decision, made in App
 * Settings → Clients: this model holds every field the catalogue names and
 * lets that configuration decide which of them a form asks for. The column
 * existing is not the same as the business using it.
 */
class Client extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'comm_email' => true,
        'comm_sms' => true,
        'comm_phone' => true,
        'marketing_email' => false,
        'marketing_sms' => false,
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'consent_recorded_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'next_booking_at' => 'datetime',
            'comm_email' => 'boolean',
            'comm_sms' => 'boolean',
            'comm_phone' => 'boolean',
            'marketing_email' => 'boolean',
            'marketing_sms' => 'boolean',
        ];
    }

    // ---------------------------------------------------------- relations

    public function preferredLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'preferred_location_id');
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'preferred_staff_id');
    }

    public function consentRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_recorded_by');
    }

    /**
     * Every phone number, the one to ring first at the top.
     *
     * Ordered here rather than at each call site so "primary first" is a
     * property of the relationship: a screen that forgot to sort would show
     * a client's old work landline as their number.
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ClientPhone::class)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ClientEmail::class)
            ->orderByDesc('is_primary')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function primaryPhone(): ?ClientPhone
    {
        return $this->phones->first(fn (ClientPhone $phone) => $phone->is_primary) ?? $this->phones->first();
    }

    public function primaryEmail(): ?ClientEmail
    {
        return $this->emails->first(fn (ClientEmail $email) => $email->is_primary) ?? $this->emails->first();
    }

    /**
     * The behavioural tags this client carries, in catalogue order.
     *
     * Read through config rather than joined to a table of labels: the
     * catalogue is where a tag's name and rule live, and a copy in the
     * database would be a second answer to what "Frequent booker" means.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function behavioralTags()
    {
        $catalogue = config('behavioral_tags.tags');

        return DB::table('client_behavioral_tag')
            ->where('client_id', $this->id)
            ->pluck('tag_key')
            ->filter(fn (string $key) => isset($catalogue[$key]))
            ->sortBy(fn (string $key) => array_search($key, array_keys($catalogue), true))
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $catalogue[$key]['label'],
                'rule' => $catalogue[$key]['rule'] ?? null,
            ])
            ->values();
    }

    /**
     * Replace the set a client carries.
     *
     * Manual assignments only: the rows are stamped as such so the rule
     * engine, when it arrives, can recalculate its own work without undoing
     * a decision somebody made by hand.
     *
     * @param  array<int, string>  $keys
     */
    public function syncBehavioralTags(array $keys): void
    {
        $allowed = array_keys(config('behavioral_tags.tags'));
        $keys = array_values(array_unique(array_intersect($keys, $allowed)));

        DB::table('client_behavioral_tag')->where('client_id', $this->id)->delete();

        if ($keys === []) {
            return;
        }

        DB::table('client_behavioral_tag')->insert(collect($keys)->map(fn (string $key) => [
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->id,
            'tag_key' => $key,
            'assigned_manually' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    /**
     * The running list of notes, newest first.    /**
     * The running list of notes, newest first.
     *
     * Separate from the client's own `notes` field, which is the standing
     * description the business configured; these are what someone wrote on a
     * particular day and signed.
     */
    public function clientNotes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest();
    }

    /**
     * How this client likes to be booked, as somebody said it.
     *
     * Kept apart from preferences(), which is the business's own list of
     * service preferences: one is chosen from a set the business maintains,
     * the other is whatever the client actually asked for.
     */
    public function bookingPreferences(): HasMany
    {
        return $this->hasMany(ClientBookingPreference::class)->orderBy('position')->orderBy('id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Every email this business has sent them.
     *
     * Not `emails` — that is the addresses on the record, and one word apart
     * from the messages sent to them is how the two get confused.
     */
    public function emailMessages(): HasMany
    {
        return $this->hasMany(ClientEmailMessage::class);
    }

    /**
     * The services this client is known to want.
     *
     * Said by somebody at the desk, not worked out from the diary. What they
     * have actually booked is a different question with a different answer —
     * see App\Support\ClientServiceHistory — and a favourite that appeared
     * because a service was booked twice is a favourite nobody chose.
     */
    public function favoriteServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'client_favorite_services')
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->orderBy('services.name');
    }

    public function preferences(): BelongsToMany
    {
        return $this->belongsToMany(ClientPreference::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClientTag::class);
    }

    // ------------------------------------------------------------- scopes

    /**
     * Everyone a booking screen should offer.
     *
     * Archived clients are excluded unless the business has said otherwise:
     * §11 keeps them out of booking search while leaving them in every
     * appointment and report that already names them.
     */
    public function scopeBookable(Builder $query, bool $includeArchived = false): Builder
    {
        return $includeArchived
            ? $query
            : $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    /**
     * Search across the fields the business enabled, per §8.
     *
     * @param  array<int, string>  $fields
     */
    public function scopeMatching(Builder $query, string $term, array $fields): Builder
    {
        if ($term === '' || $fields === []) {
            return $query;
        }

        $columns = array_intersect_key([
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'mobile' => 'mobile',
            'email' => 'email',
            'client_id' => 'client_ref',
        ], array_flip($fields));

        return $query->where(function (Builder $q) use ($columns, $term, $fields) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%'.$term.'%');
            }

            /**
             * The cache columns hold the primary number and address, so a
             * client found by their work email would otherwise be
             * unfindable. Searching the rows as well means every number and
             * address a business recorded can find the person it belongs to.
             */
            if (in_array('mobile', $fields, true)) {
                $q->orWhereHas('phones', fn (Builder $phone) => $phone->where('number', 'like', '%'.$term.'%'));
            }

            if (in_array('email', $fields, true)) {
                $q->orWhereHas('emails', fn (Builder $email) => $email->where('email', 'like', '%'.$term.'%'));
            }
        });
    }

    // ------------------------------------------------------------ display

    /**
     * The client's name, written the way the business chose.
     *
     * One method rather than each screen deciding, for the same reason money
     * has one formatter: a client listed as "Osei, Amara" on the calendar and
     * "Amara Osei" in the client list reads as two people.
     */
    public function displayName(?string $format = null): string
    {
        $format ??= ClientSettings::forTenant($this->tenant ?? tenant())->name_format;

        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);
        $preferred = trim((string) $this->preferred_name) ?: $first;

        return trim(match ($format) {
            'last_first' => $last === '' ? $first : $last.', '.$first,
            'first_initial' => $last === '' ? $first : $first.' '.mb_substr($last, 0, 1).'.',
            'preferred_last' => trim($preferred.' '.$last),
            default => trim($first.' '.$last),
        });
    }

    /**
     * Initials for the avatar, from the legal name rather than the display
     * one — "Osei, Amara" would otherwise initial as "OA".
     */
    public function initials(): string
    {
        $letters = collect([$this->first_name, $this->last_name])
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr(trim($part), 0, 1)))
            ->join('');

        return $letters !== '' ? $letters : '?';
    }

    public function statusLabel(): string
    {
        return ClientOptions::statuses()[$this->status] ?? $this->status;
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'styledesk_badge--active',
            self::STATUS_INACTIVE => 'styledesk_badge--setup',
            default => 'styledesk_badge--soon',
        };
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    // ------------------------------------------------------------ contact

    /**
     * Replace this client's phone numbers with the rows given.
     *
     * Everything the spec asks about priority is settled here rather than in
     * the form, the controller and whatever imports clients later: exactly
     * one number is primary, marking a new one demotes the old one, and the
     * primary is copied back to `clients.mobile` so every screen that shows
     * "the client's number" shows the same one. Three call sites each doing
     * their own version of that is three chances to leave a record with two
     * primaries and no way to say which is meant.
     *
     * @param  array<int, array{number?: string|null, country?: string|null, type?: string|null, is_primary?: mixed}>  $rows
     */
    public function syncPhones(array $rows): void
    {
        $clean = collect($rows)
            ->map(fn (array $row) => [
                'number' => trim((string) ($row['number'] ?? '')),
                'country' => $row['country'] ?? null,
                'type' => in_array($row['type'] ?? null, array_keys(config('clients.phone_types')), true)
                    ? $row['type']
                    : 'mobile',
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ])
            ->filter(fn (array $row) => $row['number'] !== '')
            // The same number twice is a mistake, and the database refuses it
            // anyway; dropping it here means a duplicated paste saves rather
            // than throwing a constraint error at someone.
            ->unique(fn (array $row) => self::compareNumber($row['number']))
            ->values();

        $this->writeContacts('phones', $clean, 'number', 'mobile');
    }

    /**
     * @param  array<int, array{email?: string|null, type?: string|null, is_primary?: mixed}>  $rows
     */
    public function syncEmails(array $rows): void
    {
        $clean = collect($rows)
            ->map(fn (array $row) => [
                'email' => Str::lower(trim((string) ($row['email'] ?? ''))),
                'type' => in_array($row['type'] ?? null, array_keys(config('clients.email_types')), true)
                    ? $row['type']
                    : 'personal',
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ])
            ->filter(fn (array $row) => $row['email'] !== '')
            ->unique('email')
            ->values();

        $this->writeContacts('emails', $clean, 'email', 'email');
    }

    /**
     * The part both kinds share: one primary, in order, cached on the client.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function writeContacts(string $relationName, $rows, string $valueKey, string $cacheColumn): void
    {
        /** @var HasMany $relation */
        $relation = $this->{$relationName}();

        /**
         * Exactly one primary, always.
         *
         * If nobody was marked, the first row is it — a client with three
         * numbers and no primary would leave every screen picking one for
         * itself. If several were marked, the first marked wins rather than
         * the last, so ticking a box never silently unticks one further up
         * the form that the reader can still see.
         */
        $primaryIndex = $rows->search(fn (array $row) => $row['is_primary']);
        $primaryIndex = $primaryIndex === false ? 0 : $primaryIndex;

        $relation->delete();

        $rows->each(function (array $row, int $index) use ($relation, $primaryIndex) {
            $relation->create(array_merge($row, [
                'tenant_id' => $this->tenant_id,
                'is_primary' => $index === $primaryIndex,
                'position' => $index,
            ]));
        });

        $this->unsetRelation($relationName);

        // The cache column, so search, the listing and every notification
        // read one place rather than each choosing among the rows.
        $this->forceFill([$cacheColumn => $rows->get($primaryIndex)[$valueKey] ?? null])->save();
    }

    /**
     * A stored number with its punctuation stripped, in SQL.
     *
     * Numbers are kept as they were typed — that is what a receptionist
     * reads back — so comparing two of them means removing the spacing on
     * both sides rather than assuming either was normalised on the way in.
     */
    private static function digitsOnly(string $column): string
    {
        return "replace(replace(replace(replace({$column}, ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    /** Digits only: how two numbers are told apart, whatever they were typed like. */
    public static function compareNumber(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?? '';
    }

    // --------------------------------------------------------- client ref

    /**
     * The next identifier for this business, as CL-000123.
     *
     * Taken from the highest number already issued rather than from a count,
     * because a count reuses the reference of a client that was removed — and
     * a reference that once meant one person and later means another is worse
     * than a gap in the sequence.
     *
     * Called inside the creating transaction, so two receptionists adding a
     * client at the same moment cannot be handed the same number.
     */
    public static function nextRef(string $tenantId): string
    {
        $prefix = config('clients.client_id.prefix');
        $padding = config('clients.client_id.padding');

        $highest = DB::table('clients')
            ->where('tenant_id', $tenantId)
            ->where('client_ref', 'like', $prefix.'%')
            ->lockForUpdate()
            ->selectRaw('max(cast(substring(client_ref, ?) as unsigned)) as top', [mb_strlen($prefix) + 1])
            ->value('top');

        return $prefix.str_pad((string) ((int) $highest + 1), $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Clients that look like this one, per §7.
     *
     * A warning, never a merge: two people can share a phone, and a wrongly
     * merged history is not something a receptionist can unpick.
     *
     * @param  array<int, string>  $rules
     * @return Collection<int, self>
     */
    public static function possibleDuplicates(string $tenantId, array $data, array $rules, ?int $ignoreId = null)
    {
        /**
         * Every address and number on the form, not only the primary pair.
         *
         * Someone whose work email is already on file is exactly the case
         * §Validation asks to warn about, and checking the primary alone
         * would walk straight past it.
         *
         * @var Collection<int, string> $emails
         */
        $emails = collect($data['emails'] ?? [])
            ->map(fn ($row) => Str::lower(trim((string) (is_array($row) ? ($row['email'] ?? '') : $row))))
            ->push(Str::lower(trim((string) ($data['email'] ?? ''))))
            ->filter()
            ->unique()
            ->values();

        $mobiles = collect($data['phones'] ?? [])
            ->map(fn ($row) => self::compareNumber(is_array($row) ? ($row['number'] ?? '') : $row))
            ->push(self::compareNumber((string) ($data['mobile'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $query = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where(function (Builder $q) use ($rules, $emails, $mobiles, $data) {
                // Starts impossible, so a rule set that matches nothing
                // returns nothing rather than returning everyone.
                $q->whereRaw('1 = 0');

                if (in_array('email', $rules, true)) {
                    foreach ($emails as $email) {
                        $q->orWhereRaw('lower(email) = ?', [$email])
                            ->orWhereHas('emails', fn (Builder $e) => $e->whereRaw('lower(email) = ?', [$email]));
                    }
                }

                if (in_array('mobile', $rules, true)) {
                    foreach ($mobiles as $mobile) {
                        $q->orWhereRaw(self::digitsOnly('mobile').' like ?', ['%'.$mobile])
                            ->orWhereHas('phones', fn (Builder $p) => $p->whereRaw(self::digitsOnly('number').' like ?', ['%'.$mobile]));
                    }
                }

                if (in_array('name_mobile', $rules, true) && $mobiles->isNotEmpty() && filled($data['first_name'] ?? null)) {
                    $q->orWhere(fn (Builder $inner) => $inner
                        ->whereRaw('lower(first_name) = ?', [Str::lower(trim((string) $data['first_name']))])
                        ->whereRaw('lower(coalesce(last_name, "")) = ?', [Str::lower(trim((string) ($data['last_name'] ?? '')))])
                        ->where(function (Builder $numbers) use ($mobiles) {
                            foreach ($mobiles as $mobile) {
                                $numbers->orWhereRaw(self::digitsOnly('mobile').' like ?', ['%'.$mobile])
                                    ->orWhereHas('phones', fn (Builder $p) => $p->whereRaw(self::digitsOnly('number').' like ?', ['%'.$mobile]));
                            }
                        }));
                }
            });

        return $query->limit(5)->get();
    }
}
