<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Support\SchedulePeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your work schedule has been published".
 *
 * One email for the whole period the manager published, however many weeks
 * that was — not one a week. Somebody who is sent four emails for a four-week
 * rota has to reassemble it themselves, and will read the last one as a
 * correction to the first.
 *
 * The period is passed in already resolved rather than rebuilt here: the
 * numbers in this email must be the ones the manager saw in the confirmation
 * dialog, and recomputing them after the fact invites the two to disagree.
 *
 * Not ShouldQueue itself — App\Jobs\SendSchedulePublishedEmail wraps the send,
 * matching how the invitation mail is delivered.
 */
class SchedulePublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{date: string, label: string, periods: array<int, string>, hours: float}>  $days
     */
    public function __construct(
        public Staff $staff,
        public string $businessName,
        public ?string $locationName,
        public string $startsOn,
        public string $endsOn,
        public int $workingDays,
        public float $totalHours,
        public array $days,
        public bool $canSignIn,
        public ?string $publishedOn = null,
        public ?string $publishedBy = null,
        /**
         * A period this person has already been sent once.
         *
         * The email says so rather than arriving as though it were the
         * first: somebody who reads "has been published" about a week they
         * were told about last Tuesday has to compare the two themselves to
         * find out what moved.
         */
        public bool $isRepublish = false,
    ) {}

    public static function forPeriod(SchedulePeriod $period, string $businessName, bool $isRepublish = false): self
    {
        $staff = $period->staff;

        return new self(
            staff: $staff,
            businessName: $businessName,
            locationName: $staff->location?->name,
            startsOn: $period->from->translatedFormat('j M Y'),
            endsOn: $period->until->translatedFormat('j M Y'),
            workingDays: $period->workingDays(),
            totalHours: $period->totalHours(),
            days: $period->days(),
            /* The link is only offered to somebody who can actually follow
               it. A "View My Schedule" button that lands on a sign-in page
               they have no account for is worse than no button. */
            canSignIn: $staff->user_id !== null && (bool) $staff->login_enabled,
            /* Read back from the period that was just committed, so the
               receipt in the email is the one the schedule page shows. */
            publishedOn: $period->publishedAt()?->translatedFormat('j M Y, g:i A'),
            publishedBy: $period->publishedBy(),
            isRepublish: $isRepublish,
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->isRepublish
            ? 'schedule.email.subject_updated'
            : 'schedule.email.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.schedule-published',
            with: [
                'staffName' => $this->staff->displayName(),
                'headline' => __($this->isRepublish
                    ? 'schedule.email.headline_updated'
                    : 'schedule.email.headline'),
                'intro' => __($this->isRepublish
                    ? 'schedule.email.intro_updated'
                    : 'schedule.email.intro', ['business' => $this->businessName]),
                'scheduleUrl' => $this->canSignIn ? route('staff.schedule', $this->staff) : null,
                'supportEmail' => config('mail.support_address'),
            ],
        );
    }
}
