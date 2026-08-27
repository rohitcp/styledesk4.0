<?php

declare(strict_types=1);

/*
| The Branding module: the form and its previews.
|
| The preview panels are representations of the app, the booking page, an email
| and a receipt — so the words inside them are sample content, and they are
| translated for the same reason the rest of the screen is: a Spanish reader
| looking at what their branding will produce should be able to read it.
*/

return [
    'title' => 'Branding',
    'intro' => 'Your logo, favicon and colours, used across StyleDesk, your booking page, confirmation and reminder emails, receipts and invoices.',
    'saved' => 'Branding settings updated successfully.',
    'save_failed' => "We couldn't save your branding. Please try again.",
    'reset_done' => 'Branding reset to the StyleDesk default.',
    'correct_fields' => 'Please correct the highlighted fields and try again.',

    'reset' => 'Reset to default branding',
    'reset_confirm' => 'Reset branding to the StyleDesk default? Your logo, favicon and colours are removed.',

    'logo' => [
        'title' => 'Business logo',
        'hint' => 'PNG, JPG, SVG or WEBP, up to 2 MB. Around 400×120 px works well. A transparent background is kept as-is.',
        'upload' => 'Upload logo',
        'replace' => 'Replace logo',
    ],

    'favicon' => [
        'title' => 'Favicon / app icon',
        'hint' => 'PNG, SVG or ICO, up to 2 MB. Use a square image — 512×512 px is ideal.',
        'upload' => 'Upload favicon',
        'replace' => 'Replace favicon',
    ],

    'upload' => [
        'uploading' => 'Uploading…',
        'removed' => 'Removed. Save to confirm.',
        'failed' => 'That file could not be uploaded.',
        'mimes' => 'Use a :formats file.',
        'too_large' => 'The file must be 2 MB or smaller.',
    ],

    'colours' => [
        'title' => 'Brand colours',
        'hint' => 'Pick a colour or type a hex value. The hover shade and the colour of text on your buttons are worked out from these.',
        'primary' => 'Primary',
        'primary_hint' => 'Buttons, links and the app bar.',
        'secondary' => 'Secondary',
        'secondary_hint' => 'Supporting highlights.',
        'accent' => 'Accent',
        'accent_hint' => 'Badges and small emphasis.',
        'picker_label' => ':name colour picker',
        'invalid' => 'Enter a hex colour, like #3d348b.',
        'contrast' => 'White text on your primary colour:',
        'contrast_warning' => 'Your app bar, booking page header and email header print white text on this colour, and at this shade it is hard to read. A darker colour usually fixes it.',
        'required_primary' => 'Choose a primary colour.',
        'required_secondary' => 'Choose a secondary colour.',
        'required_accent' => 'Choose an accent colour.',
    ],

    /*
     * WCAG grades. Left in their standard form: "AA" is the name of a
     * conformance level, not an English word, and a designer looking for it
     * in a spec will be looking for those two letters.
     */
    'grades' => [
        'aaa' => 'AAA',
        'aa' => 'AA',
        'large' => 'Large text only',
        'fails' => 'Fails',
    ],

    'preview' => [
        'title' => 'Preview',
        'hint' => 'Updates as you change the colours above.',
        'trial' => '0 days remaining in your free trial',
        'in_app' => 'In StyleDesk',
        'booking_page' => 'Your booking page',
        'emails' => 'Confirmation, reminder and invitation emails',
        'receipts' => 'Receipts and invoices',

        'your_logo' => 'Your logo',
        'book_appointment' => 'Book appointment',
        'confirmed' => 'Confirmed',
        'new' => 'New',
        'link_sentence' => 'A link looks like :link.',
        'this_one' => 'this one',

        'book_online' => 'Book online, any time',
        'select' => 'Select',
        'continue' => 'Continue',
        'minutes' => ':count min',

        'email_subject' => 'Your appointment is confirmed',
        'email_body' => 'Thursday 4 September, 2:00 PM with Priya at :business.',
        'view_appointment' => 'View appointment',
        'email_footer' => 'Sent by :business via StyleDesk',

        'receipt' => 'Receipt',
        'tax' => 'Tax',
        'total' => 'Total',

        'where_used' => 'Where this is used',
        'where_used_body' => 'The StyleDesk app, your booking pages, appointment confirmations, reminders, team invitations, receipts, invoices, gift cards and client notifications.',
        'representations' => 'The panels above are representations, not the templates themselves.',
    ],
];
