@props(['for'])

{{--
    Visibility toggle for a password field.

    One component so every password input behaves identically. The icon states
    describe the *current* state of the field, which is the convention users
    expect: while the password is masked the eye-off icon is shown, and it
    becomes a plain eye once the characters are readable.

    aria-pressed carries the state for assistive tech, and the label changes
    with it, so the control is not communicated by the icon alone.
--}}
<button type="button"
        data-password-toggle="{{ $for }}"
        aria-controls="{{ $for }}"
        aria-pressed="false"
        aria-label="Show password"
        {{ $attributes->merge(['class' => 'absolute right-2 top-1/2 -translate-y-1/2 sd-iconbtn grid']) }}>

    {{-- Shown while the password is hidden. --}}
    <svg data-icon-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 4l16 16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        <path d="M9.9 5.8A9.5 9.5 0 0112 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 01-3 3.8M6.5 8.2A17 17 0 002.5 12S6 18.5 12 18.5c.8 0 1.5-.1 2.2-.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
    </svg>

    {{-- Shown once the password is readable. --}}
    <svg data-icon-visible hidden width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.7"/>
        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/>
    </svg>
</button>
