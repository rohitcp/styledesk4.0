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
    /**
     * Every range this class knows how to work out.
     *
     * A vocabulary, not a menu: which of these a screen offers is the
     * screen's own business, and they do not all want the same list. Sales is
     * read a month at a time; resource utilization is read a few days at a
     * time, because "is that room busy" is a question about this week.
     */
    public const PRESETS = ['today', 'tomorrow', 'yesterday', 'last_3', 'last_7', 'week', 'month', 'custom'];

    /** What the Sales page offers, in the order it offers them. */
    public const SALES = ['today', 'yesterday', 'week', 'month', 'custom'];

    /** What resource utilization offers. */
    public const UTILIZATION = ['today', 'yesterday', 'last_3', 'last_7', 'custom'];

    /*
    | What staff utilization offers.
    |
    | Forwards as well as back, which is what tells it from the resource list:
    | "is tomorrow covered" is the question a manager opens the rota with, and
    | it is one only this screen can answer.
    */
    public const STAFF = ['today', 'tomorrow', 'week', 'month', 'custom'];

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
    public static function fromRequest(?string $preset, ?string $from, ?string $to, string $fallback = 'month'): self
    {
        $preset = in_array($preset, self::PRESETS, true) ? $preset : $fallback;

        if ($preset === 'custom') {
            $start = self::parse($from);
            $end = self::parse($to);

            if ($start === null || $end === null || $start->gt($end)) {
                return self::preset($fallback);
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
            /* Forwards: a rota is read ahead as often as behind. */
            'tomorrow' => [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()],
            /* Today and the days before it, today included: somebody asking
               for the last three days at four in the afternoon means today's
               takings as well. */
            'last_3' => [now()->subDays(2)->startOfDay(), now()->endOfDay()],
            'last_7' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
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
