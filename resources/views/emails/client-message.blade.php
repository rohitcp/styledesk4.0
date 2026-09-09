{{--
    A message the salon wrote, in the salon's own livery.

    The business layout, not the account one: this is a letter from the client's
    salon about their appointment, and StyleDesk branding on it would read as a
    stranger writing to them about their haircut.

    The body is whatever the sender typed. It is printed as plain text with line
    breaks preserved — never as HTML — because the drawer is a plain textarea and
    anything else would let a pasted fragment of markup rewrite the email.
--}}
@php
    $brand = \App\Support\EmailBrand::for($email->tenant, $email->sender_name);
@endphp

<x-mail.business :brand="$brand"
                 :headline="$email->subject"
                 :preheader="\Illuminate\Support\Str::limit(strip_tags($bodyText), 120)"
                 :footer-lines="array_filter([$email->tenant?->business_phone, $email->tenant?->website])">

    <div style="margin:0; white-space:pre-wrap;">{{ $bodyText }}</div>
</x-mail.business>
