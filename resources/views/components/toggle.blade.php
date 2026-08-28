{{--
    <x-toggle name="is_private" label="Mark as private" /> — a switch.

    A checkbox by every measure that matters: it posts like one, it is one to
    the keyboard and to a screen reader, and the switch is what the label
    draws instead of a box. Only the appearance differs, so nothing that
    reads the form has to know it was styled.

    Used where a setting takes effect as itself — "mark this note private" —
    rather than where one option is being chosen from several, which is what
    <x-choice> is for.
--}}
@props([
    'name',
    'label',
    'hint' => null,
    'checked' => false,
    'value' => '1',
])

<label {{ $attributes->class('styledesk_toggle') }}>
    {{-- The unchecked value, so an unticked switch says "off" rather than
         saying nothing and leaving the server to guess. --}}
    <input type="hidden" name="{{ $name }}" value="0">

    <input type="checkbox" name="{{ $name }}" value="{{ $value }}"
           class="styledesk_toggle__input" @checked($checked)>

    <span class="styledesk_toggle__track" aria-hidden="true">
        <span class="styledesk_toggle__knob"></span>
    </span>

    <span class="min-w-0 flex-1">
        <span class="styledesk_toggle__label">{{ $label }}</span>

        @if ($hint)
            <span class="styledesk_toggle__hint">{{ $hint }}</span>
        @endif
    </span>
</label>
