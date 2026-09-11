<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Messaging\MessagingService;
use App\Messaging\SmsProviders;
use App\Models\BackofficeAuditLog;
use App\Models\PlatformSmsSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Back Office → SMS.
 *
 * The platform's own carrier account: which company carries every business's
 * text messages, the keys to reach them, and a way to prove it works before
 * a salon finds out it does not.
 *
 * This is not a salon's screen and must never become one. A business decides
 * what it sends (App Settings → SMS); this decides what carries it, what it
 * costs and whose account it is billed to.
 *
 * Every credential here is encrypted at rest and none of them is ever sent
 * back to the browser — a field that arrives blank means "leave it alone",
 * not "clear it", because the alternative is a form that wipes a working key
 * every time somebody changes the sender number.
 */
class SmsController extends Controller
{
    public function index(): View
    {
        $settings = PlatformSmsSettings::current();

        return view('backoffice.sms.index', [
            'settings' => $settings,
            'providers' => SmsProviders::selectable(),
            /* What the application will actually do right now, which is not
               always what this screen says: a developer's machine never
               reaches a carrier whatever is configured here. */
            'live' => SmsProviders::mayReachACarrier(),
            'resolved' => SmsProviders::resolve()->name(),
            'sender' => SmsProviders::senderNumber(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'provider' => ['required', Rule::in(SmsProviders::selectable())],

            'telnyx_key' => ['nullable', 'string', 'max:255'],
            'telnyx_public_key' => ['nullable', 'string', 'max:255'],
            'telnyx_from' => ['nullable', 'string', 'max:32'],

            'clicksend_username' => ['nullable', 'string', 'max:255'],
            'clicksend_key' => ['nullable', 'string', 'max:255'],
            'clicksend_webhook_secret' => ['nullable', 'string', 'max:255'],
            'clicksend_from' => ['nullable', 'string', 'max:32'],
        ]);

        $settings = PlatformSmsSettings::current();

        /* A blank secret means "leave it alone", never "clear it". The form
           cannot show what is stored — that is the point of encrypting it —
           so an empty box is an untouched box. Clearing one is done by
           switching the provider off, not by deleting characters. */
        foreach (['telnyx_key', 'telnyx_public_key', 'clicksend_username', 'clicksend_key', 'clicksend_webhook_secret'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $settings->{$secret} = $data[$secret];
            }
        }

        $settings->fill([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'provider' => $data['provider'],
            'telnyx_from' => self::digits($data['telnyx_from'] ?? null),
            'clicksend_from' => self::digits($data['clicksend_from'] ?? null),
        ]);

        /* Refused rather than saved half-ready. Switching the platform to a
           carrier with no key in it is every business's messages failing at
           once, and the first anybody hears of it is a client who never got
           their confirmation. */
        if ($settings->is_enabled && ! $settings->isReady($settings->provider)) {
            throw ValidationException::withMessages([
                'provider' => __('backoffice.sms.not_ready', [
                    'fields' => implode(', ', $settings->missingFor($settings->provider)),
                ]),
            ]);
        }

        $before = PlatformSmsSettings::current();

        $settings->updated_by = $request->user('backoffice')?->id;
        $settings->save();

        SmsProviders::forget();

        /* Switching carrier changes where every business's messages go and
           what they cost. Not a change anybody makes anonymously. */
        BackofficeAuditLog::record(
            'sms.settings_updated',
            $request->user('backoffice'),
            $settings,
            before: ['provider' => $before->provider, 'enabled' => $before->is_enabled],
            after: ['provider' => $settings->provider, 'enabled' => $settings->is_enabled],
        );

        return back()->with('status', __('backoffice.sms.saved'));
    }

    /**
     * Does the account answer?
     *
     * A cheap read against the carrier — no message, no cost — so somebody
     * can tell a wrong key from a wrong number before spending anything.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        $settings = PlatformSmsSettings::current();
        $provider = $settings->provider;

        try {
            $ok = match ($provider) {
                'telnyx' => Http::withToken((string) $settings->telnyx_key)
                    ->acceptJson()->timeout(15)
                    ->get(rtrim((string) config('services.telnyx.url'), '/').'/messaging_profiles', ['page[size]' => 1])
                    ->successful(),
                'clicksend' => Http::withBasicAuth(
                    (string) $settings->clicksend_username,
                    (string) $settings->clicksend_key,
                )->acceptJson()->timeout(15)
                    ->get(rtrim((string) config('services.clicksend.url'), '/').'/account')
                    ->successful(),
                default => false,
            };
        } catch (Throwable $exception) {
            return back()->with('error', __('backoffice.sms.test_failed', ['reason' => $exception->getMessage()]));
        }

        return $ok
            ? back()->with('status', __('backoffice.sms.connected', ['provider' => $provider]))
            : back()->with('error', __('backoffice.sms.not_connected', ['provider' => $provider]));
    }

    /**
     * One real message, to prove the whole path.
     *
     * Through the ordinary service, so it exercises what a booking
     * confirmation exercises — and it obeys the same safety rule: on a
     * developer's machine it goes to the SMS catcher and nowhere near a
     * carrier, whatever this screen is set to.
     */
    public function sendTest(Request $request, MessagingService $messaging): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
        ]);

        $to = self::digits($data['to']) ?? '';

        if (strlen(ltrim($to, '+')) < 7) {
            throw ValidationException::withMessages(['to' => __('backoffice.sms.bad_number')]);
        }

        $message = $messaging->send($to, __('backoffice.sms.test_body'), 'test');

        BackofficeAuditLog::record(
            'sms.test_sent',
            $request->user('backoffice'),
            after: ['to' => $to, 'provider' => $message?->provider],
        );

        return $message?->status === 'sent'
            ? back()->with('status', __('backoffice.sms.test_sent', [
                'number' => $to,
                'provider' => $message->provider,
            ]))
            : back()->with('error', __('backoffice.sms.test_failed', [
                'reason' => $message?->error_message ?: __('backoffice.sms.no_reason'),
            ]));
    }

    /** A number as a carrier wants it: digits and a leading plus. */
    private static function digits(?string $number): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', (string) $number) ?? '';

        return $clean === '' ? null : $clean;
    }
}
