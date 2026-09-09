{{--
    One read-only label/value pair.

    `value` is rendered as text, never as a disabled input. A greyed-out form
    control says "you may not change this"; plain text says "this is what it
    is", which is what a view-mode page means. Empty values become an em dash
    rather than a blank, so a missing answer is visibly missing rather than
    looking like a rendering fault.
--}}
@props(['label', 'value' => null, 'manage' => null, 'manageLabel' => 'Manage'])

<div class="py-3 border-b border-line last:border-0">
    <dt class="text-[12px] text-sub">{{ $label }}</dt>
    <dd class="mt-1 flex items-start gap-3">
        <span class="min-w-0 flex-1 text-[14px] text-head">
            @if (trim((string) $slot) !== '')
                {{ $slot }}
            @elseif (filled($value))
                {{ $value }}
            @else
                <span class="text-faint">—</span>
            @endif
        </span>

        {{-- Configured elsewhere. The value is shown here for context, but the
             link sends people to the module that owns it rather than letting
             two screens write the same field. --}}
        @if ($manage)
            <a href="{{ $manage }}" class="shrink-0 text-[13px] font-medium text-link hover:underline">{{ $manageLabel }}</a>
        @endif
    </dd>
</div>
