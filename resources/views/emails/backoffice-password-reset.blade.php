{{--
    The Backoffice password reset link.

    The house account-mail layout, like the sign-in code: this one really is
    from StyleDesk to a StyleDesk employee, so the branded business layout
    would be wrong.
--}}
<x-mail.auth :headline="__('backoffice.email.reset_headline')"
             :preheader="__('backoffice.email.reset_preheader')"
             :greeting="__('backoffice.email.reset_greeting', ['name' => $admin->name])"
             :ctaLabel="__('backoffice.email.reset_cta')"
             :ctaUrl="$resetUrl">

    <p style="margin:0;">{{ __('backoffice.email.reset_intro') }}</p>

    <p style="margin:24px 0 0 0;">{{ __('backoffice.email.reset_expiry', ['minutes' => $minutes]) }}</p>

    {{-- The URL in full, because a button that does not render leaves the
         reader with no way in. Learned on the invitation email. --}}
    <p style="margin:24px 0 6px 0; font-size:12px; color:#6b7280;">
        {{ __('backoffice.email.reset_fallback') }}
    </p>
    <p style="margin:0; font-size:12px; word-break:break-all;">
        <a href="{{ $resetUrl }}" style="color:#3d348b;">{{ $resetUrl }}</a>
    </p>

    {{-- The line that matters if this email was not expected: somebody has
         their address and is standing at the door. --}}
    <p style="margin:24px 0 0 0;">{{ __('backoffice.email.reset_unexpected') }}</p>
</x-mail.auth>
