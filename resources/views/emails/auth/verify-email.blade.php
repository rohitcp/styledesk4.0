@php
    /* Stated in hours once the window is a round hour, because "expires in 60
       minutes" is a thing a reader has to convert and "expires in 1 hour" is
       not. */
    $expiry = $expiresInMinutes % 60 === 0
        ? ($expiresInMinutes / 60).' hour'.($expiresInMinutes === 60 ? '' : 's')
        : $expiresInMinutes.' minutes';
@endphp

<x-mail.auth
    headline="You’re almost there"
    :greeting="'Hi '.$firstName.','"
    preheader="Verify your email address to finish setting up your StyleDesk account."
    cta-label="Verify Email Address"
    :cta-url="$url"
    :supporting="'This link and code expire in '.$expiry.'. If you didn’t create a StyleDesk account, you can safely ignore this email.'">

    <p style="margin:0 0 16px 0;">Thanks for creating your StyleDesk account.</p>
    <p style="margin:0;">Please verify your email address so we know it’s really you and can finish setting up your account.</p>

    {{-- The code, for a reader whose mail client opens links in a browser that
         is not the one they signed up in — the button would show them a login
         form there, while the code works on the page they already have open.

         Letter-spaced with a monospace stack so the digits are unambiguous
         and easy to copy: a six-digit code is only useful if it can be read
         back correctly on the first try. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
        <tr>
            <td align="center" style="padding:0;">
                <p style="margin:0 0 10px 0; font-size:13px; line-height:1.6; color:#6b7280;">
                    Or enter this confirmation code on the verification screen:
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                    <tr>
                        <td align="center" bgcolor="#f5f4fb" style="border-radius:10px; padding:14px 28px;">
                            <span style="font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace; font-size:28px; font-weight:700; letter-spacing:6px; color:#0f0f10;">{{ $code }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
