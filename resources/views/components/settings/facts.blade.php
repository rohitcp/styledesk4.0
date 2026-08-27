{{--
    A list of label/value pairs that shows what is there.

    Empty values are collected into one muted line at the foot instead of
    taking a row each. On a staff profile most optional fields are unset, and
    a column of em-dashes costs exactly as much vertical space as real
    information while carrying none — but "which fields are blank" is still
    worth knowing, so it is summarised rather than dropped.

    Pass facts as label => value. A value may be a string, or a closure
    returning markup for anything that is not plain text.
--}}
@props(['facts' => []])

@php
    $filled = [];
    $empty = [];

    foreach ($facts as $label => $value) {
        // A closure is markup; it is only included when the caller says so by
        // passing it at all, so it always counts as filled.
        if ($value instanceof Closure) {
            $filled[$label] = $value;

            continue;
        }

        filled($value) ? $filled[$label] = $value : $empty[] = $label;
    }
@endphp

<dl class="mt-1">
    @foreach ($filled as $label => $value)
        <div class="py-2.5 border-b border-line last:border-0">
            <dt class="text-[12px] text-sub">{{ $label }}</dt>
            <dd class="mt-0.5 text-[14px] text-head">
                @if ($value instanceof Closure)
                    {!! $value() !!}
                @else
                    {{ $value }}
                @endif
            </dd>
        </div>
    @endforeach

    @if ($empty)
        <div class="pt-3 @if ($filled) mt-1 border-t border-line @endif">
            <p class="text-[12px] text-faint">Not set: {{ implode(', ', $empty) }}</p>
        </div>
    @endif
</dl>
