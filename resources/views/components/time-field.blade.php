{{--
    A time of day, using the design system's column-scroll picker.

    Not a native time input, for the same reason x-date-field is not a native
    date one: the native control renders differently in every browser and
    ignores the app's own field styling. The shared picker (TimePicker.vue) is
    what the business-hours screens already use, so a shift's start time looks
    and behaves like a location's opening time.

    The value is held and posted as 24-hour "HH:MM" whatever the display
    format, so the server sees one format and date_format:H:i is all the
    validation it needs.
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'hint' => null,
    'minuteStep' => 5,
])

@php
    /** The ISO value, whatever it arrived as: old input, a Carbon time, a string. */
    $timeValue = $value instanceof \DateTimeInterface
        ? $value->format('H:i')
        : substr((string) ($value ?? ''), 0, 5);

    $timeProps = [
        'name' => $name,
        'modelValue' => old($name, $timeValue),
        'minuteStep' => (int) $minuteStep,
        'ariaLabel' => $label ?? $name,
        /* Translated here rather than defaulted in the component: the panel
           is the one place a reader is told they may type instead of scroll. */
        'searchPlaceholder' => __('common.type_a_time'),
    ];
@endphp

<div {{ $attributes }}>
    @if ($label)
        <span class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $label }}@if ($required) <span class="text-danger">*</span>@endif
        </span>
    @endif

    <div data-vue-component="TimePicker" data-props='@json($timeProps)'></div>

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif

    <p data-error-for="{{ $name }}" role="alert" class="mt-1.5 text-[12px] text-danger"
       @unless ($errors->has($name)) hidden @endunless>{{ $errors->first($name) }}</p>
</div>
