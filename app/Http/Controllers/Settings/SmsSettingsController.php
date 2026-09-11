<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Messaging\MessagingService;
use App\Models\SmsSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * App Settings → SMS.
 *
 * Four questions: whether to text at all, from which number, which of the
 * messages to send, and what the business is willing to spend doing it.
 *
 * The master switch is separate from the per-message ones on purpose. A salon
 * that has to stop texting for a week — a carrier problem, a bill, a
 * complaint — must be able to, and come back to find its choices exactly as
 * it left them.
 *
 * Nothing here reaches the carrier. The account, the shared number and its
 * 10DLC registration are StyleDesk's; what this screen decides is what the
 * business does with them.
 */
class SmsSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->permit($request);

        $settings = SmsSettings::forTenant($request->user()->tenant);

        return view('settings.sms.index', [
            'settings' => $settings,
            /* Every message including the ones nothing can produce yet. The
               screen draws those disabled and says so, which tells the truth
               about what is planned rather than pretending a reminder
               already goes out. */
            'messages' => config('sms.messages'),
            'reminderHours' => config('sms.reminder_hours'),
            'used' => $settings->usedThisMonth(),
            'segments' => $settings->segmentsThisMonth(),
            /* The reader's own number, where the staff directory has one.
               Somebody testing this is almost always texting themselves, and
               retyping their own mobile is the friction that stops people
               checking. */
            'testNumber' => $request->user()?->staffRecord()?->phone,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->permit($request);

        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'messages' => ['nullable', 'array'],
            /* Only a message StyleDesk can actually produce. Accepting a
               switch for one it cannot would leave a business believing its
               clients were being reminded. */
            'messages.*' => [Rule::in(SmsSettings::availableMessages())],
            'reminder_hours' => ['nullable', 'array'],
            'reminder_hours.*' => [Rule::in(config('sms.reminder_hours'))],
            'birthday_send_at' => ['nullable', 'date_format:H:i'],
            /* A ceiling, not a target. Nought would mean "never send", which
               is what the master switch is for. */
            'monthly_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'alert_percent' => ['required', 'integer', 'min:10', 'max:100'],
        ]);

        SmsSettings::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'messages' => array_values($data['messages'] ?? []),
                /* Sorted and de-duplicated: two reminders at the same hour is
                   the same reminder twice, and the client gets two texts. */
                'reminder_hours' => collect($data['reminder_hours'] ?? [])
                    ->map(fn ($hour) => (int) $hour)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
                'birthday_send_at' => $data['birthday_send_at'] ?? '09:00',
                'monthly_limit' => $data['monthly_limit'] ?? null,
                'alert_percent' => (int) $data['alert_percent'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('sms.settings.saved'),
        ]);
    }

    /**
     * A number as a carrier wants it: digits and a leading plus.
     *
     * Numbers are written a dozen ways and every one of them is the same
     * number. Normalised on the way in so the log, the webhook and the
     * carrier all agree about which it is.
     */
    private static function digits(?string $number): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', (string) $number) ?? '';

        return $clean === '' ? null : $clean;
    }

    /**
     * Send one message, to prove the wiring.
     *
     * Straight through rather than queued: somebody pressing this is asking
     * "does it work", and an answer that arrives on a worker a minute later
     * is not an answer. Everything else about it is the ordinary path — the
     * same service, the same provider, the same record in the log — because
     * a test that took a shortcut would prove the shortcut works.
     *
     * No client is named, so no consent is consulted: this goes to a number
     * the person at the keyboard typed, which is their own to test with.
     */
    public function test(Request $request, MessagingService $messaging): RedirectResponse
    {
        $this->permit($request);

        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
        ]);

        $to = self::digits($data['to']) ?? '';

        /* Loose on purpose. Numbers are written a dozen ways and the carrier
           is the authority on which are real; what is refused here is what
           obviously is not one. */
        if (strlen(ltrim($to, '+')) < 7) {
            throw ValidationException::withMessages([
                'to' => __('sms.settings.test_bad_number'),
            ]);
        }

        $message = $messaging->send(
            $to,
            __('sms.settings.test_body', ['business' => $request->user()->tenant?->name ?? config('app.name')]),
            'test',
        );

        /* Whether it actually went, and by what. A green tick over a message
           the provider refused is the one outcome this button must never
           produce. */
        return back()->with('toast', $message?->status === 'sent'
            ? [
                'type' => 'success',
                'message' => __('sms.settings.test_sent', [
                    'number' => $message->to_number,
                    'provider' => $message->provider,
                ]),
            ]
            : [
                'type' => 'error',
                'message' => __('sms.settings.test_failed', [
                    'reason' => $message?->error_message ?: __('sms.settings.test_unknown'),
                ]),
            ]);
    }

    /**
     * Deciding what a business says to a client's phone — and what it spends
     * saying it — is its own authority, on top of the settings group's.
     */
    private function permit(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('sms.manage_settings', 'own'), 403);
    }
}
