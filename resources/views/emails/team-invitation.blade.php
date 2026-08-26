{{--
    Invitation email.

    Hand-written table layout with inline styles rather than the Tailwind app
    stylesheet: mail clients strip <link> and most of them ignore <style>, so a
    class-based layout arrives unstyled. Everything visual here has to survive
    being read by Outlook, which is why nothing depends on flexbox or CSS
    variables.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You're invited to join {{ $businessName }} on StyleDesk</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f0f10;">
    {{-- Preheader: the grey line inboxes show next to the subject. Hidden in
         the body itself, otherwise it reads as a stray duplicate sentence. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $inviterName }} invited you to join {{ $businessName }} as {{ $roleLabel }}.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f7f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

                    <tr>
                        <td style="background-color:#3d348b; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:17px; font-weight:700; letter-spacing:-0.2px;">StyleDesk</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <h1 style="margin:0 0 8px 0; font-size:20px; line-height:1.35; font-weight:700; color:#0f0f10;">
                                You're invited to join {{ $businessName }}
                            </h1>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#4b5563;">
                                {{ $inviterName }} has invited you to join their team on StyleDesk, the booking
                                and scheduling app {{ $businessName }} runs on.
                            </p>
                        </td>
                    </tr>

                    @if ($inviteMessage)
                        {{-- The inviter's own words, quoted so it is obvious
                             which part of this email a person wrote. --}}
                        <tr>
                            <td style="padding:16px 28px 0 28px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-left:3px solid #3d348b; padding:2px 0 2px 14px;">
                                            <p style="margin:0; font-size:14px; line-height:1.6; color:#374151; font-style:italic;">
                                                {{ $inviteMessage }}
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:20px 28px 0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="background-color:#fafbfc; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:13px; color:#6b7280; width:38%;">Business</td>
                                    <td style="padding:14px 16px; font-size:13px; color:#0f0f10; font-weight:600;">{{ $businessName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 16px 14px 16px; font-size:13px; color:#6b7280;">Your role</td>
                                    <td style="padding:0 16px 14px 16px; font-size:13px; color:#0f0f10; font-weight:600;">{{ $roleLabel }}</td>
                                </tr>
                                @if ($locationName)
                                    <tr>
                                        <td style="padding:0 16px 14px 16px; font-size:13px; color:#6b7280;">Location</td>
                                        <td style="padding:0 16px 14px 16px; font-size:13px; color:#0f0f10; font-weight:600;">{{ $locationName }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="padding:24px 28px 4px 28px;">
                            <a href="{{ $acceptUrl }}"
                               style="display:inline-block; background-color:#3d348b; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:13px 28px; border-radius:8px;">
                                Join Team
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px 0 28px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                This invitation expires on <strong style="color:#0f0f10;">{{ $expiresAt }}</strong>
                                ({{ $expiresInDays }} days from when it was sent). After that, ask
                                {{ $inviterName }} to send you a new one.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px 28px 28px;">
                            {{-- The URL in full, because a button that does not
                                 render leaves the reader with no way in. --}}
                            <p style="margin:0 0 6px 0; font-size:12px; color:#6b7280;">
                                If the button does not work, copy and paste this address into your browser:
                            </p>
                            <p style="margin:0; font-size:12px; word-break:break-all;">
                                <a href="{{ $acceptUrl }}" style="color:#3d348b;">{{ $acceptUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px; background-color:#fafbfc; border-top:1px solid #e5e7eb;">
                            <p style="margin:0 0 6px 0; font-size:12px; line-height:1.6; color:#6b7280;">
                                This invitation was sent to {{ $email }}. If you were not expecting it you can
                                ignore this email — nothing happens until you accept.
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                                Need help? Reply to this email or contact {{ $supportEmail }}.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0 0; font-size:11px; color:#9ca3af;">
                    &copy; {{ date('Y') }} StyleDesk. All rights reserved.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
