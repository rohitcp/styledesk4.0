@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'required' => false, 'autofocus' => false])

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
            value="{{ $isPassword ? '' : old($name) }}"
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
