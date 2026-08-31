<x-mail.auth
    headline="Confirm your new email address"
    :greeting="'Hi '.$firstName.','"
    preheader="Confirm this address so we can move your StyleDesk account to it."
    cta-label="Confirm Email Address"
    :cta-url="$url"
    :supporting="'This link expires in '.$expiresInHours.' hours. Until you confirm, your StyleDesk account keeps its current email address — so if you didn’t ask for this change, you can safely ignore this email.'">

    <p style="margin:0 0 16px 0;">Someone asked to move a StyleDesk account to this address ({{ $newEmail }}).</p>
    <p style="margin:0;">Confirm it and we’ll use it for signing in and for everything we send you from then on.</p>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
