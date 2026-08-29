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
    /**
     * Live-validation rules, in the shared syntax — "required|email|max:255".
     * Passed through to the input, where resources/js/live-validation.js
     * reads them; a field without them behaves exactly as it always has.
     */
    'rules' => null,
    /**
     * Shown but not editable.
     *
     * readonly, never disabled: a disabled field is left out of the submitted
     * data, so a form that showed an address this way would post without one.
     */
    'readonly' => false,
    'hint' => null,
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
            @if ($rules) data-rules="{{ $rules }}" @endif
            @if ($readonly) readonly aria-readonly="true" @endif
            value="{{ $isPassword ? '' : old($name, $value) }}"
            @class(['sd-input', 'has-suffix' => $isPassword, 'is-readonly' => $readonly])
        >

        @if ($isPassword)
            <x-password-toggle :for="$name" />
        @endif
    </div>

    @if ($hint)
        <p class="mt-1.5 text-[12px] text-sub">{{ $hint }}</p>
    @endif

    {{-- One box for both kinds of message. The server writes into it when it
         refuses a submission, and the browser writes into the same one while
         the reader types — so a message never appears twice and never in two
         different places. --}}
    <p data-error-for="{{ $name }}" role="alert" class="mt-1.5 text-[12px] text-danger"
       @unless ($errors->has($name)) hidden @endunless>{{ $errors->first($name) }}</p>
</div>
