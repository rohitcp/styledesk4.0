{{--
    The Backoffice sign-in code.

    The house account-mail layout, because this one really is from StyleDesk to
    a StyleDesk employee — the branded business layout would be wrong here.
--}}
<x-mail.auth :headline="__('backoffice.email.code_headline')"
             :preheader="__('backoffice.email.code_preheader')"
             :greeting="__('backoffice.email.code_greeting', ['name' => $admin->name])">

    <p style="margin:0;">{{ __('backoffice.email.code_intro') }}</p>

    {{-- Large, spaced and monospaced: it is read off one screen and typed into
         another, and 0 beside O at 14px is a support call. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin-top:24px;">
        <tr>
            <td align="center" style="padding:20px 16px; background-color:#f4f4f7; border-radius:12px;">
                <p style="margin:0; font-size:32px; line-height:1.1; font-weight:700; letter-spacing:8px;
                          font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace; color:#0f0f10;">
                    {{ $code }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0 0;">{{ __('backoffice.email.code_expiry', ['minutes' => $minutes]) }}</p>

    {{-- The line that matters if this email was not expected: somebody has
         their address and is standing at the door. --}}
    <p style="margin:16px 0 0 0;">{{ __('backoffice.email.code_unexpected') }}</p>
</x-mail.auth>
