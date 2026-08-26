@props(['name', 'label', 'type' => 'text', 'autocomplete' => null, 'required' => false, 'autofocus' => false])

<div>
    <label for="{{ $name }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @if ($autofocus) autofocus @endif
        value="{{ $type === 'password' ? '' : old($name) }}"
        class="sd-input"
    >
    @error($name)
        <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>
    @enderror
</div>
