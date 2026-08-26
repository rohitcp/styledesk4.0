@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'required' => false, 'autofocus' => false])

<div class="styledesk_field">
    <label class="styledesk_field__label" for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @if ($autofocus) autofocus @endif
        value="{{ $type === 'password' ? '' : old($name) }}"
        class="styledesk_input @error($name) styledesk_input--invalid @enderror"
    >
    @error($name)
        <p class="styledesk_field__error">{{ $message }}</p>
    @enderror
</div>
