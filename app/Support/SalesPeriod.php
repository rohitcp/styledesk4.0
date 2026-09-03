<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The date range the Sales page is showing, and the one before it.
 *
 * One place, because two questions depend on it and they must agree: what the
 * figures cover, and what "+8.4% vs previous period" is comparing against. A
 * previous period worked out separately is how a comparison ends up measuring
 * a fortnight against a month.
 *
 * The previous period is always the same length as the current one, ending the
 * instant it begins — so "this month" is compared with the month before it,
 * and a seven-day custom range with the seven days before that.
 */
class SalesPeriod
{
    public const PRESETS = ['today', 'yesterday', 'week', 'month', 'custom'];

    public function __construct(
        public readonly string $preset,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    /**
     * Read the range off the request, falling back to this month.
     *
     * A custom range with either end missing, or with the ends the wrong way
     * round, falls back rather than querying on nonsense — a page that shows
     * an empty table because somebody typed a date badly looks like a page
     * with no sales.
     */
    public static function fromRequest(?string $preset, ?string $from, ?string $to): self
    {
        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'month';

        if ($preset === 'custom') {
            $start = self::parse($from);
            $end = self::parse($to);

            if ($start === null || $end === null || $start->gt($end)) {
                return self::preset('month');
            }

            return new self('custom', $start->startOfDay(), $end->endOfDay());
        }

        return self::preset($preset);
    }

    public static function preset(string $preset): self
    {
        [$from, $to] = match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        return new self($preset, $from, $to);
    }

    /** The same length again, ending where this one starts. */
    public function previous(): self
    {
        /* Counted in whole days, not seconds.
           Every range this class produces is a run of whole days, and second
           arithmetic on one is off by one at both ends — the gap between
           00:00:00 and 23:59:59 is a second short of a day, and rounding it
           back moves the answer by a whole one. */
        $days = $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;

        $end = $this->from->copy()->subDay()->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();

        return new self($this->preset, $start, $end);
    }

    public function label(): string
    {
        return $this->preset === 'custom'
            ? $this->from->translatedFormat('j M Y').' – '.$this->to->translatedFormat('j M Y')
            : __('sales.periods.'.$this->preset);
    }

    /** @return array<string, string> */
    public function toQuery(): array
    {
        return array_filter([
            'period' => $this->preset,
            'from' => $this->preset === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->preset === 'custom' ? $this->to->toDateString() : null,
        ]);
    }

    private static function parse(?string $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }
    }
}
