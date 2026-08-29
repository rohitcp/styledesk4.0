{{--
    A date field, using the design system's calendar picker.

    Not a native date input. The native control renders differently in every
    browser, ignores the app's own field styling, and — the reason it matters
    for a date of birth — offers no way to reach 1974 except by paging back
    six hundred months. The shared picker (SD.datePicker) puts the year in a
    dropdown, so any birth year is one step away.

    The visible field is readonly and shows MM/DD/YYYY; the ISO value the
    server validates travels in a hidden input kept in step with it. The two
    are separate because the picker's display format is a presentation choice
    and the wire format is not, and a form should never have to reparse what a
    control drew.

    Options reach the picker through data-dp-options rather than a per-page
    script, so a screen adds a date field by writing one tag.
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    // Forms that mark every optional field say so here too, rather than
    // leaving this one field silent about it.
    'optional' => false,
    'hint' => null,
    'min' => null,
    'max' => null,
    'minYear' => null,
    'maxYear' => null,
    'openTo' => null,
    'clearable' => true,
    'placeholder' => null,
    'dialogLabel' => null,
    'id' => null,
    /** Live-validation rules, applied to the hidden input the form posts. */
    'rules' => null,
])

@php
    $dateId = $id ?? $name;

    /** The ISO value, whatever it arrived as: old input, a Carbon date, a string. */
    $dateValue = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');

    $dateOptions = array_filter([
        'min' => $min,
        'max' => $max,
        'minYear' => $minYear === null ? null : (int) $minYear,
        'maxYear' => $maxYear === null ? null : (int) $maxYear,
        'openTo' => $openTo,
        'dialogLabel' => $dialogLabel ?? __('common.choose_a_date'),
        'clearable' => $clearable,
    ], fn ($option) => $option !== null);

    /**
     * false has to survive array_filter, which drops it along with null.
     * Set afterwards so `clearable="false"` actually reaches the picker
     * rather than silently leaving the Clear button in place.
     */
    $dateOptions['clearable'] = (bool) $clearable;

    /**
     * Month and weekday names in the reader's language, from Carbon rather
     * than a translation file — it already carries them for every locale the
     * app offers, and a second hand-written list is a second thing to drift.
     */
    $dateLocale = app()->getLocale();

    $dateOptions['labels'] = [
        'months' => collect(range(1, 12))
            ->map(fn (int $month) => \Illuminate\Support\Str::ucfirst(
                \Carbon\Carbon::create(2000, $month, 1)->locale($dateLocale)->isoFormat('MMMM')
            ))->all(),
        // Sunday first, matching the grid the picker draws.
        'dow' => collect(range(0, 6))
            ->map(fn (int $day) => \Illuminate\Support\Str::ucfirst(
                \Carbon\Carbon::create(2024, 1, 7)->addDays($day)->locale($dateLocale)->isoFormat('dd')
            ))->all(),
        'clear' => __('common.clear'),
        'today' => __('common.today'),
        'month' => __('common.month'),
        'year' => __('common.year'),
        'previousMonth' => __('common.previous_month'),
        'nextMonth' => __('common.next_month'),
    ];

    /**
     * Day-first everywhere except the United States, which is the one place
     * 03/04 means March. Read off the locale rather than asked for at each
     * call site, so no screen has to decide.
     */
    $dateOptions['order'] = str_starts_with($dateLocale, 'en') && ! str_contains($dateLocale, 'GB') ? 'mdy' : 'dmy';
    $datePlaceholder = $dateOptions['order'] === 'mdy' ? 'MM/DD/YYYY' : 'DD/MM/YYYY';
@endphp

<div {{ $attributes }}>
    @if ($label)
        <label for="{{ $dateId }}" class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @elseif ($optional)
                <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            @endif
        </label>
    @endif

    <div class="relative" data-datepicker data-dp-options='@json($dateOptions)'>
        <input id="{{ $dateId }}" type="text" readonly placeholder="{{ $placeholder ?? $datePlaceholder }}"
               class="sd-input is-picker has-suffix @error($name) is-error @enderror"
               data-dp-input data-dp-for="{{ $dateId }}_value"
               @if ($dateValue !== '') data-value="{{ $dateValue }}" @endif
               @if ($hint) aria-describedby="{{ $dateId }}-hint" @endif>

        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-faint pointer-events-none">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </span>

        <div class="sd-cal" data-dp-cal hidden></div>
    </div>

    {{-- What the form actually posts. --}}
    <input type="hidden" id="{{ $dateId }}_value" name="{{ $name }}" value="{{ $dateValue }}"
           @if ($rules) data-rules="{{ $rules }}" @endif>

    @if ($hint)
        <p id="{{ $dateId }}-hint" class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif

    <p data-error-for="{{ $rules ? $dateId.'_value' : $name }}" role="alert"
       class="mt-1.5 text-[12px] text-danger"
       @unless ($errors->has($name)) hidden @endunless>{{ $errors->first($name) }}</p>
</div>
