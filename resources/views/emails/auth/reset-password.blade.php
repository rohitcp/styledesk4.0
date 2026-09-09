<x-mail.auth
    headline="Need a new password?"
    :greeting="'Hi '.$firstName.','"
    preheader="Choose a new password for your StyleDesk account."
    cta-label="Reset Password"
    :cta-url="$url"
    :supporting="'For your security, this link will expire in '.$expiresInMinutes.' minutes. If you didn’t request a password reset, you can ignore this email — your password will stay the same.'">

    <p style="margin:0 0 16px 0;">We received a request to reset the password for your StyleDesk account.</p>
    <p style="margin:0;">Use the button below to choose a new password.</p>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
