{{--
    The secure sign-in email.

    Written and ready; nothing sends it yet, because magic-link sign-in is a
    tab on the login page and not a route. When it becomes one, it has its
    email already.
--}}
<x-mail.auth
    headline="Ready to sign in?"
    :greeting="'Hi '.$firstName.','"
    preheader="Your secure StyleDesk sign-in link."
    cta-label="Sign In to StyleDesk"
    :cta-url="$url"
    supporting="This sign-in link will expire shortly for your security. If you didn’t request this sign-in, you can safely ignore this email.">

    <p style="margin:0 0 16px 0;">We received a request to sign in to your StyleDesk account.</p>
    <p style="margin:0;">Use the button below to continue securely.</p>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
