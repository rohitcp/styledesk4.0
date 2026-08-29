<x-mail.auth
    headline="Your password has been updated"
    :greeting="'Hi '.$firstName.','"
    preheader="Your StyleDesk password was changed."
    cta-label="Go to StyleDesk"
    :cta-url="route('login')"
    supporting="If you didn’t make this change, please contact StyleDesk Support immediately.">

    <p style="margin:0 0 16px 0;">Your StyleDesk account password was successfully changed.</p>
    <p style="margin:0;">If you made this change, no further action is required.</p>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
