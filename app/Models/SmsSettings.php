<?php

declare(strict_types=1);

namespace App\Models;

use App\Messaging\SmsProviders;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * What this business sends by text, and when.
 *
 * Switching SMS off stops the sending. It erases nothing: the choices stay,
 * and every message already sent stays readable in the log — a salon that has
 * to stop texting for a week must be able to, without losing which of the
 * messages it had picked.
 */
class SmsSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'sms_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'messages' => 'array',
            'reminder_hours' => 'array',
            'monthly_limit' => 'integer',
            'alert_percent' => 'integer',
        ];
    }

    /**
     * This business's settings, whether or not anybody has saved any.
     *
     * Unsaved rather than created on read: a business that has never opened
     * the screen has no row, and reading the defaults must not quietly write
     * one. The screen's save is what makes it real.
     */
    public static function forTenant(?Tenant $tenant): self
    {
        $existing = $tenant === null
            ? null
            : self::query()->where('tenant_id', $tenant->getTenantKey())->first();

        return $existing ?? new self([
            'tenant_id' => $tenant?->getTenantKey(),
        ] + self::defaults());
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'is_enabled' => false,
            /* Left null so the row does not freeze a copy of the platform
               number. senderNumber() falls back to it when reading. */
            'sender_number' => null,
            'registration_status' => 'not_started',
            'messages' => collect(config('sms.messages'))
                ->filter(fn (array $message) => $message['default'] && $message['available'])
                ->keys()
                ->all(),
            'reminder_hours' => [24],
            'birthday_send_at' => '09:00',
            'monthly_limit' => null,
            'alert_percent' => 80,
        ];
    }

    /**
     * May this business send this kind of message right now?
     *
     * Three gates, and all of them have to be open: the business has SMS on,
     * it has chosen this message, and StyleDesk can actually produce it.
     */
    public function sends(string $type): bool
    {
        return $this->is_enabled
            && in_array($type, (array) $this->messages, true)
            && (bool) config('sms.messages.'.$type.'.available', false);
    }

    /** Only the messages StyleDesk can produce today. */
    public static function availableMessages(): array
    {
        return collect(config('sms.messages'))
            ->filter(fn (array $message) => $message['available'])
            ->keys()
            ->all();
    }

    public function registrationLabel(): string
    {
        return __('sms.registration.'.$this->registration_status);
    }

    public function registrationClass(): string
    {
        return config('sms.registration.'.$this->registration_status.'.class', 'styledesk_badge--soon');
    }

    /**
     * The number this business sends from.
     *
     * Its own, where one has been assigned; otherwise the platform's default
     * number, which is what every business shares until each has a 10DLC
     * registration of its own.
     *
     * Null is a real answer and the caller has to handle it: sending without
     * a number is refused by the carrier, and the error names neither the
     * number nor the setting that should hold it.
     */
    public function senderNumber(): ?string
    {
        /* The active carrier's own number. Each carrier owns its numbers,
           so this follows the provider rather than sitting in one shared
           setting — see App\Messaging\SmsProviders. */
        return SmsProviders::senderNumber();
    }

    /** What has gone out this month, for the usage figure on the screen. */
    public function usedThisMonth(): int
    {
        return SmsMessage::query()
            ->whereNotIn('status', ['queued'])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /** Segments, not messages: that is what a carrier bills. */
    public function segmentsThisMonth(): int
    {
        return (int) SmsMessage::query()
            ->whereNotIn('status', ['queued'])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('segments');
    }
}
