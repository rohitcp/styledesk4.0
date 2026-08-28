@props([
    'name',
    'label',
    'type' => 'text',
    'autocomplete' => null,
    'required' => false,
    'autofocus' => false,
    /**
     * What the field holds when nothing has been submitted — the stored
     * value on an edit form.
     *
     * old() still wins, so a refused submission comes back with what was
     * typed rather than with what was stored. Without this the component
     * could only ever render a blank field, which is why every edit form so
     * far has written its inputs by hand.
     */
    'value' => null,
    'maxlength' => null,
])

@php
    // Password fields get a visibility toggle, so they need a positioning
    // context and room on the right for the button.
    $isPassword = $type === 'password';
@endphp

<div>
    <label for="{{ $name }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ $label }}</label>

    <div @class(['relative' => $isPassword])>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($maxlength) maxlength="{{ $maxlength }}" @endif
            value="{{ $isPassword ? '' : old($name, $value) }}"
            @class(['sd-input', 'has-suffix' => $isPassword])
        >

        @if ($isPassword)
            <x-password-toggle :for="$name" />
        @endif
    </div>

    @error($name)
        <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
    @enderror
</div>
