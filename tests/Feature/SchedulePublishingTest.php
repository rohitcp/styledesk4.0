<?php

namespace Tests\Feature;

use App\Jobs\SendSchedulePublishedEmail;
use App\Mail\SchedulePublishedMail;
use App\Models\Location;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\SchedulePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Saving a schedule as a draft, and publishing it.
 *
 * The distinction these tests exist to protect is that assigning a schedule
 * and telling somebody about it are separate acts. A rota that emailed the
 * staff member the moment a manager tried a pattern would make the page
 * unusable for the thing it is for — trying patterns — so nothing leaves the
 * building until Publish is confirmed.
 */
class SchedulePublishingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Acme Salon', 'slug' => 'acme']);
        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);
    }

    private function owner(): User
    {
        $user = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh();
    }

    private function member(array $attributes = []): Staff
    {
        return Staff::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'role' => 'service-provider',
            'location_id' => $this->location->id,
            'is_active' => true,
        ]);
    }

    /** One eight-hour day, nine to five, on the given date. */
    private function shift(Staff $member, string $date, array $overrides = []): StaffShift
    {
        return StaffShift::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'staff_id' => $member->id,
            'location_id' => $this->location->id,
            'date' => $date,
            'starts_at' => '09:00', 'ends_at' => '17:00',
        ]);
    }

    /** Monday to Friday of next week, which is what the page opens on. */
    private function week(Staff $member): array
    {
        $from = now()->next('Monday')->startOfDay();

        for ($i = 0; $i < 5; $i++) {
            $this->shift($member, $from->copy()->addDays($i)->toDateString());
        }

        return ['from' => $from->toDateString(), 'until' => $from->copy()->addDays(6)->toDateString()];
    }

    // ---------------------------------------------------------------- draft

    /**
     * A shift arrives as a draft.
     *
     * The default lives on the model rather than only in the assign action,
     * because a shift added any other way — the one-off shifts form, a future
     * importer — is equally something nobody has been told about yet.
     */
    public function test_a_new_shift_starts_as_a_draft(): void
    {
        $member = $this->member();
        $shift = $this->shift($member, now()->addDay()->toDateString());

        $this->assertSame('draft', $shift->publish_status);
        $this->assertNull($shift->published_at);
        $this->assertFalse($shift->isPublished());
    }

    public function test_assigning_a_schedule_writes_drafts_and_sends_nothing(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $from = now()->next('Monday')->startOfDay();

        $this->actingAs($owner)->post(route('staff.schedule.assign', $member), [
            'from' => $from->toDateString(),
            'until' => $from->copy()->addDays(4)->toDateString(),
            'days' => [
                $from->toDateString() => [
                    'working' => '1',
                    'periods' => [['starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 0]],
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(['draft'], StaffShift::query()->pluck('publish_status')->unique()->all());
        Queue::assertNotPushed(SendSchedulePublishedEmail::class);
    }

    public function test_saving_a_draft_keeps_it_unpublished_and_sends_nothing(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.draft', $member), $range)
            ->assertRedirect()
            ->assertSessionHas('toast.message', __('schedule.draft_saved'));

        $this->assertSame(['draft'], StaffShift::query()->pluck('publish_status')->unique()->all());
        Queue::assertNotPushed(SendSchedulePublishedEmail::class);
    }

    /**
     * Save Draft never un-tells somebody about a week they have been emailed.
     * Reverting a published shift is what an edit does, not what a button
     * named "keep this as a draft" does.
     */
    public function test_saving_a_draft_leaves_published_shifts_alone(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        $this->actingAs($owner)->post(route('staff.schedule.draft', $member), $range);

        $this->assertSame(['published'], StaffShift::query()->pluck('publish_status')->unique()->all());
    }

    public function test_saving_a_draft_over_an_empty_period_says_so(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.draft', $member), [
                'from' => now()->toDateString(),
                'until' => now()->addDays(6)->toDateString(),
            ])
            ->assertSessionHas('toast.message', __('schedule.nothing_to_save'));
    }

    // ------------------------------------------------------------ publishing

    public function test_publishing_marks_the_period_and_emails_the_staff_member(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.publish', $member), $range)
            ->assertRedirect()
            ->assertSessionHas('toast.message', __('schedule.published', ['name' => 'Susan Pena']));

        $this->assertSame(['published'], StaffShift::query()->pluck('publish_status')->unique()->all());
        $this->assertNotNull(StaffShift::query()->first()->published_at);

        Queue::assertPushed(SendSchedulePublishedEmail::class, function (SendSchedulePublishedEmail $job) {
            return $job->email === 'susan@acme.test'
                && $job->mail->workingDays === 5
                && $job->mail->totalHours === 40.0;
        });
    }

    /**
     * The receipt travels with the schedule.
     *
     * When it was sent and who sent it are the two things a staff member
     * needs to tell one version of a week from another, so they are in the
     * email rather than only on the page they may not be able to open.
     */
    public function test_the_email_carries_the_publication_receipt(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $this->week($member));

        Queue::assertPushed(SendSchedulePublishedEmail::class, function (SendSchedulePublishedEmail $job) use ($owner) {
            return $job->mail->publishedOn !== null
                && $job->mail->publishedBy === $owner->name
                /* Days off are in the list too: a schedule showing only the
                   working days leaves the reader counting backwards to find
                   out whether Saturday is theirs. */
                && collect($job->mail->days)->contains(fn (array $day) => $day['periods'] === []);
        });
    }

    /**
     * The email leaves in the request that published the schedule.
     *
     * A queued job only leaves the building once a worker picks it up, and an
     * installation without one leaves the manager told the staff member was
     * emailed while the row sits in the jobs table. Publishing is the act of
     * telling somebody; it does not wait on infrastructure that may not be
     * running. Set SCHEDULE_MAIL_QUEUE once a worker is part of the
     * deployment — see config/shifts.php.
     */
    public function test_the_email_is_sent_in_the_publishing_request(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $this->week($member));

        Queue::assertPushed(
            SendSchedulePublishedEmail::class,
            fn (SendSchedulePublishedEmail $job) => $job->connection === 'sync',
        );
    }

    /**
     * A mail provider having a bad day does not undo the publication.
     *
     * Sending inside the request is what makes this worth stating: the
     * shifts are already written and the staff member can see them, so the
     * failure changes the sentence the manager is shown and nothing else.
     */
    public function test_a_failed_send_still_publishes_and_says_so(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        Mail::shouldReceive('to')->andThrow(new RuntimeException('The mail provider is down.'));
        /* Twice: the job says the send failed, the publishing action says the
           schedule went out without it. */
        Log::shouldReceive('error')->atLeast()->once();

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.publish', $member), $this->week($member))
            ->assertRedirect()
            ->assertSessionHas('toast.message', __('schedule.published_email_failed', ['name' => 'Susan Pena']));

        $this->assertSame(['published'], StaffShift::query()->pluck('publish_status')->unique()->all());
    }

    /**
     * The hours are a sentence, not a plural set.
     *
     * `__` on a pluralised string hands the reader the whole
     * "{1} :count Hour|[2,*] :count Hours" line, which is what the email used
     * to say where the total belonged.
     */
    public function test_the_email_states_the_hours_in_words(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $period = SchedulePeriod::for(
            $member,
            CarbonImmutable::parse($range['from']),
            CarbonImmutable::parse($range['until']),
        );

        $this->actingAs($owner);
        $html = SchedulePublishedMail::forPeriod($period, 'Acme Salon')->render();

        $this->assertStringContainsString('40 Hours', $html);
        $this->assertStringNotContainsString('[2,*]', $html);
    }

    /**
     * A week somebody has already been told about is an update, not news.
     *
     * Two emails headed "has been published" leave the reader comparing them
     * line by line to find out which is current.
     */
    public function test_republishing_says_the_schedule_was_updated(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        Queue::assertPushed(SendSchedulePublishedEmail::class, fn (SendSchedulePublishedEmail $job) => $job->mail->isRepublish === false
            && $job->mail->envelope()->subject === __('schedule.email.subject'));

        /* Edited, which puts the period back into "changes not published",
           and then sent again. */
        StaffShift::query()->update(['publish_status' => 'draft', 'ends_at' => '18:00']);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        Queue::assertPushed(SendSchedulePublishedEmail::class, fn (SendSchedulePublishedEmail $job) => $job->mail->isRepublish === true
            && $job->mail->envelope()->subject === __('schedule.email.subject_updated'));
    }

    /**
     * Assigning over a week that has already been sent is a republication
     * too, even though the rows themselves are written from scratch.
     */
    public function test_reassigning_over_a_published_week_sends_the_updated_subject(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->startOfDay();

        $this->shift($member, $date->toDateString(), [
            'publish_status' => 'published',
            'published_at' => now(),
            'published_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('staff.schedule.assign', $member), [
            'from' => $date->toDateString(),
            'until' => $date->toDateString(),
            'publish' => 1,
            'days' => [$date->toDateString() => [
                'working' => '1',
                'periods' => [['starts_at' => '10:00', 'ends_at' => '18:00', 'break_minutes' => 0]],
            ]],
        ])->assertRedirect();

        Queue::assertPushed(SendSchedulePublishedEmail::class, fn (SendSchedulePublishedEmail $job) => $job->mail->isRepublish === true);
    }

    /**
     * One email for the period the manager chose, however many weeks it
     * covers. Four emails for a four-week rota would leave the reader to
     * reassemble it, and the last would read as a correction to the first.
     */
    public function test_publishing_a_multi_week_period_sends_one_email_for_the_whole_range(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $from = now()->next('Monday')->startOfDay();

        /* Four weeks, one working day in each. */
        foreach ([0, 7, 14, 21] as $offset) {
            $this->shift($member, $from->copy()->addDays($offset)->toDateString());
        }

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), [
            'from' => $from->toDateString(),
            'until' => $from->copy()->addDays(27)->toDateString(),
        ])->assertRedirect();

        Queue::assertPushed(SendSchedulePublishedEmail::class, 1);
        Queue::assertPushed(SendSchedulePublishedEmail::class, function (SendSchedulePublishedEmail $job) {
            return $job->mail->workingDays === 4
                && $job->mail->totalHours === 32.0
                && count($job->mail->days) === 28;
        });
    }

    /** Shifts outside the chosen range are somebody else's week. */
    public function test_publishing_leaves_shifts_outside_the_period_alone(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $outside = $this->shift($member, now()->next('Monday')->addDays(10)->toDateString());

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        $this->assertSame('draft', $outside->refresh()->publish_status);
    }

    public function test_publishing_an_empty_period_publishes_nothing(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.publish', $member), [
                'from' => now()->toDateString(),
                'until' => now()->addDays(6)->toDateString(),
            ])
            ->assertSessionHas('toast.message', __('schedule.nothing_to_publish'));

        Queue::assertNotPushed(SendSchedulePublishedEmail::class);
    }

    /**
     * No address is a reason to tell the manager, not a reason to refuse the
     * publish: the schedule is real and they can go and fix the address.
     */
    public function test_publishing_without_an_address_still_publishes_and_says_so(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member(['email' => null]);
        $range = $this->week($member);

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->post(route('staff.schedule.publish', $member), $range)
            ->assertSessionHas('toast.message', __('schedule.published_without_email', ['name' => 'Susan Pena']));

        $this->assertSame(['published'], StaffShift::query()->pluck('publish_status')->unique()->all());
        Queue::assertNotPushed(SendSchedulePublishedEmail::class);
    }

    /** The work address wins where there is one — it is the one they read at work. */
    public function test_the_work_email_is_preferred(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member(['work_email' => 'susan@salon.test']);
        $range = $this->week($member);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        Queue::assertPushed(
            SendSchedulePublishedEmail::class,
            fn (SendSchedulePublishedEmail $job) => $job->email === 'susan@salon.test',
        );
    }

    // -------------------------------------------------------- after publishing

    /**
     * Editing a published shift makes it a change waiting to be communicated,
     * and sends nothing on its own — the manager decides when.
     */
    public function test_editing_a_published_shift_makes_it_a_pending_change(): void
    {
        Queue::fake();

        $owner = $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        $shift = StaffShift::query()->first();

        $this->actingAs($owner)->patch(route('shifts.update', $shift), [
            'staff_id' => $member->id,
            'date' => $shift->date->toDateString(),
            'starts_at' => '10:00', 'ends_at' => '17:00',
            'type' => 'regular', 'status' => 'scheduled', 'break_minutes' => 0,
        ])->assertRedirect();

        $shift->refresh();

        $this->assertSame('draft', $shift->publish_status);
        /* Kept, so the page can tell "never sent" from "sent, then edited". */
        $this->assertNotNull($shift->published_at);
        $this->assertTrue($shift->hasUnpublishedChanges());

        /* One job, from the publish above — the edit added none of its own. */
        Queue::assertPushed(SendSchedulePublishedEmail::class, 1);
    }

    /**
     * Both actions ask before they act.
     *
     * Save Draft is the quieter of the two, but it sits beside a button that
     * emails a colleague — and a secondary button next to a consequential one
     * is the button people press by reflex.
     */
    public function test_both_actions_confirm_before_they_run(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $from = now()->next('Monday')->startOfDay();
        $this->week($member);

        $this->actingAs($owner)
            ->get(route('staff.schedule', $member).'?'.$this->monthOf($from))
            ->assertOk()
            ->assertSee(__('schedule.confirm_draft.title'))
            ->assertSee(__('schedule.confirm_draft.intro'))
            /* The publish dialog is built in the browser from data-props, so
               its copy travels with the page rather than being rendered.
               Matched on a distinctive fragment: the full sentence carries an
               apostrophe, which is escaped on the way into the JSON. */
            ->assertSee(__('schedule.confirm.title'), false)
            ->assertSee('official working schedule', false);
    }

    // -------------------------------------------------------------- the page

    public function test_the_schedule_page_reports_the_state_of_the_period(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $from = now()->next('Monday')->startOfDay();

        /* Nothing scheduled: no draft banner and nothing to publish. */
        $url = route('staff.schedule', $member).'?'.$this->monthOf($from);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertDontSee('data-schedule-state', false)
            ->assertDontSee(route('staff.schedule.draft', $member), false);

        $this->week($member);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-schedule-state="draft"', false)
            ->assertSee(__('schedule.draft_badge'))
            /* Above the grid and below it, so a long period does not have to
               be scrolled back to the top to act on what was just read. */
            ->assertSeeInOrder([
                'data-schedule-state="draft"',
                'data-grid',
                'data-schedule-state="draft"',
            ], false);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), [
            'from' => $from->toDateString(), 'until' => $from->copy()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertSee('data-schedule-state="published"', false)
            ->assertSee(__('schedule.published_on', ['date' => now()->translatedFormat('M j, Y')]))
            /* Nothing left to keep as a draft once the week has gone out. */
            ->assertDontSee(route('staff.schedule.draft', $member), false);
    }

    /**
     * The grid's rows come from its own endpoint, one per date.
     *
     * A split day is still one row, with both its periods and their combined
     * hours; a day off is a row too, because a list of only the working days
     * hides the gaps and the gaps are half of what a rota is read for.
     */
    public function test_the_grid_returns_a_row_for_every_date(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->startOfDay();

        $this->shift($member, $date->toDateString(), ['starts_at' => '09:00', 'ends_at' => '13:00']);
        $this->shift($member, $date->toDateString(), ['starts_at' => '16:00', 'ends_at' => '20:00']);

        $response = $this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?'.$this->monthOf($date))
            ->assertOk();

        $rows = collect($response->json('data'));

        $this->assertSame($date->daysInMonth, $response->json('total'));

        $worked = $rows->firstWhere('id', $date->toDateString());

        $this->assertSame(__('schedule.working'), $worked['working']);
        $this->assertStringContainsString('9:00 AM – 1:00 PM', $worked['time']);
        $this->assertStringContainsString('4:00 PM – 8:00 PM', $worked['time']);
        $this->assertSame(__('schedule.hours_short', ['count' => 8.0]), $worked['hours']);
        $this->assertSame(__('schedule.publish_statuses.draft'), $worked['status']);
        $this->assertNull($worked['published_on']);
        $this->assertNull($worked['published_by']);

        /* A day off is a known quantity, not an unknown one. */
        $off = $rows->first(fn (array $row) => $row['working'] === __('schedule.not_working'));

        $this->assertSame(__('schedule.hours_short', ['count' => 0]), $off['hours']);
        $this->assertSame('0', $off['bookings']);
        $this->assertNull($off['time']);
    }

    /** A published date carries who sent it and when. */
    public function test_the_grid_reports_published_metadata(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->startOfDay();
        $range = $this->week($member);

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $range);

        $row = collect($this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?'.$this->monthOf($date))
            ->json('data'))->firstWhere('id', $date->toDateString());

        $this->assertSame(__('schedule.publish_statuses.published'), $row['status']);
        $this->assertNotNull($row['published_on']);
        $this->assertSame($owner->name, $row['published_by']);
    }

    /**
     * A published day carries a padlock beside its date.
     *
     * Once somebody has been emailed a day, changing it is telling them
     * something different rather than filling in a blank — and the row that
     * says so is the one the manager reaches for the actions on.
     */
    public function test_a_published_day_is_marked_locked_in_the_grid(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->startOfDay();

        $this->shift($member, $date->toDateString());

        $rows = fn () => collect($this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?'.$this->monthOf($date))
            ->json('data'));

        /* A draft is not locked; nor is a day nobody works. */
        $drafted = $rows();
        $this->assertFalse($drafted->firstWhere('id', $date->toDateString())['date_locked']);
        $this->assertFalse(
            $drafted->first(fn (array $row) => $row['working'] === __('schedule.not_working'))['date_locked'],
        );

        $this->actingAs($owner)->post(route('staff.schedule.publish', $member), $this->week($member));

        $published = $rows()->firstWhere('id', $date->toDateString());

        $this->assertTrue($published['date_locked']);
        $this->assertSame(__('schedule.locked'), $published['locked_label']);
    }

    /** The grid pages over dates, so a year is 365 rows however many shifts it holds. */
    public function test_the_grid_pages_over_dates(): void
    {
        $owner = $this->owner();
        $member = $this->member();

        $response = $this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?period=year&year=2026&size=50&page=2')
            ->assertOk();

        $this->assertSame(365, $response->json('total'));
        $this->assertSame(8, $response->json('last_page'));
        $this->assertCount(50, $response->json('data'));
    }

    /** Every row offers Edit and the disabled leave action; only worked days offer Delete. */
    public function test_row_actions_depend_on_whether_the_day_is_worked(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->startOfDay();

        $this->shift($member, $date->toDateString());

        $rows = collect($this->actingAs($owner)
            ->getJson(route('staff.schedule.data', $member).'?'.$this->monthOf($date))
            ->json('data'));

        $worked = collect($rows->firstWhere('id', $date->toDateString())['menu']);
        $off = collect($rows->first(fn (array $row) => $row['working'] === __('schedule.not_working'))['menu']);

        $this->assertTrue($worked->contains('label', __('schedule.delete_day')));
        $this->assertFalse($off->contains('label', __('schedule.delete_day')));

        /* Coming soon, and disabled rather than hidden: the reader is told the
           feature is planned instead of wondering where it went. */
        foreach ([$worked, $off] as $menu) {
            $this->assertTrue($menu->contains(
                fn (array $item) => ($item['label'] ?? null) === __('schedule.mark_on_leave')
                    && ($item['disabled'] ?? false) === true,
            ));
        }
    }

    /** Deleting a date takes its shifts off the rota. */
    public function test_a_date_can_be_deleted_from_the_grid(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $date = now()->next('Monday')->toDateString();

        $this->shift($member, $date);

        $this->actingAs($owner)
            ->from(route('staff.schedule', $member))
            ->delete(route('staff.schedule.day.destroy', [$member, $date]))
            ->assertRedirect();

        $this->assertSame(0, StaffShift::query()->count());
    }

    /** The listing's filters, for a month containing the given date. */
    private function monthOf(Carbon $date): string
    {
        return http_build_query(['period' => 'month', 'year' => $date->year, 'month' => $date->month]);
    }

    // -------------------------------------------------------- the period sums

    /**
     * A cancelled shift stays on the page as a fact about the week, but it is
     * not hours anybody works — so it counts towards neither the total nor
     * the working days, and never on its own holds the period in draft.
     */
    public function test_a_cancelled_shift_counts_towards_nothing(): void
    {
        $member = $this->member();
        $from = now()->next('Monday')->startOfDay();

        $this->shift($member, $from->toDateString(), ['publish_status' => 'published', 'published_at' => now()]);
        $this->shift($member, $from->copy()->addDay()->toDateString(), ['status' => 'cancelled']);

        $period = SchedulePeriod::for(
            $member,
            CarbonImmutable::parse($from->toDateString()),
            CarbonImmutable::parse($from->copy()->addDays(6)->toDateString()),
        );

        $this->assertSame(1, $period->workingDays());
        $this->assertSame(8.0, $period->totalHours());
        $this->assertSame(SchedulePeriod::PUBLISHED, $period->state());
    }

    public function test_the_period_reports_its_length_in_weeks(): void
    {
        $member = $this->member();
        $from = CarbonImmutable::parse(now()->next('Monday')->toDateString());

        foreach ([1 => 6, 2 => 13, 3 => 20, 4 => 27] as $weeks => $days) {
            $period = SchedulePeriod::for($member, $from, $from->addDays($days));

            $this->assertSame($weeks, $period->weeks());
        }
    }

    // ------------------------------------------------------------- the email

    public function test_the_email_carries_the_whole_period_day_by_day(): void
    {
        $owner = $this->owner();
        $member = $this->member();
        $from = CarbonImmutable::parse(now()->next('Monday')->toDateString());

        $this->shift($member, $from->toDateString(), ['starts_at' => '09:00', 'ends_at' => '13:00']);
        $this->shift($member, $from->toDateString(), ['starts_at' => '16:00', 'ends_at' => '20:00']);

        $mail = SchedulePublishedMail::forPeriod(
            SchedulePeriod::for($member->load('location'), $from, $from->addDays(6)),
            'Acme Salon',
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Acme Salon', $rendered);
        $this->assertStringContainsString('Riverside', $rendered);
        $this->assertStringContainsString('Susan Pena', $rendered);
        $this->assertStringContainsString('9:00 AM – 1:00 PM', $rendered);
        $this->assertStringContainsString('4:00 PM – 8:00 PM', $rendered);
        /* Days off are listed too, or the reader counts backwards to find out
           whether Saturday is theirs. */
        $this->assertStringContainsString(__('schedule.email.not_working'), $rendered);
        $this->assertSame(__('schedule.email.subject'), $mail->envelope()->subject);
    }

    /**
     * The "View My Schedule" button is only offered to somebody who can
     * follow it. A link landing on a sign-in page they have no account for is
     * worse than no link.
     */
    public function test_the_email_only_links_to_the_app_for_somebody_who_can_sign_in(): void
    {
        $member = $this->member();
        $from = CarbonImmutable::parse(now()->next('Monday')->toDateString());
        $this->shift($member, $from->toDateString());

        $period = fn (Staff $staff) => SchedulePeriod::for($staff->load('location'), $from, $from->addDays(6));

        $this->assertStringNotContainsString(
            __('schedule.email.cta'),
            SchedulePublishedMail::forPeriod($period($member), 'Acme Salon')->render(),
        );

        $user = User::create([
            'first_name' => 'Susan', 'last_name' => 'Pena',
            'email' => 'susan@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $member->forceFill(['user_id' => $user->id, 'login_enabled' => true])->save();

        $this->assertStringContainsString(
            __('schedule.email.cta'),
            SchedulePublishedMail::forPeriod($period($member->fresh()), 'Acme Salon')->render(),
        );
    }

    // --------------------------------------------------------- authorisation

    public function test_somebody_who_cannot_update_the_staff_member_cannot_publish(): void
    {
        Queue::fake();

        $this->owner();
        $member = $this->member();
        $range = $this->week($member);

        $colleague = User::create([
            'first_name' => 'Ravi', 'last_name' => 'Colleague',
            'email' => 'ravi@acme.test', 'password' => 'Str0ng!Pass',
        ]);
        $colleague->markEmailAsVerified();
        $colleague->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $colleague->id,
            'first_name' => 'Ravi', 'last_name' => 'Colleague',
            'email' => $colleague->email, 'role' => 'service-provider',
            'location_id' => $this->location->id,
        ]);

        $this->actingAs($colleague->fresh())
            ->post(route('staff.schedule.publish', $member), $range)
            ->assertForbidden();

        $this->assertSame(['draft'], StaffShift::query()->pluck('publish_status')->unique()->all());
        Queue::assertNotPushed(SendSchedulePublishedEmail::class);
    }
}
