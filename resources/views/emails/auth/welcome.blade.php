<x-mail.auth
    :headline="'Welcome to StyleDesk, '.$firstName"
    preheader="Your StyleDesk account is ready."
    cta-label="Open StyleDesk"
    :cta-url="route('login')">

    <p style="margin:0 0 16px 0;">Your account is ready.</p>
    <p style="margin:0;">StyleDesk helps you manage your clients, services, appointments, team and business from one place.</p>

    <x-slot:support>If you have any questions, contact StyleDesk Support.</x-slot:support>
</x-mail.auth>
