<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
|
| Email campaigns and the provider that delivers them. Labels live in the
| marketing language file, so a business running in Spanish gets Spanish
| without a second list to keep in step.
|
*/

return [

    /*
    | Who actually delivers the mail.
    |
    | Null by default and deliberately so: StyleDesk sends nothing until a
    | business has connected a provider and had its sending address verified.
    | A campaign that "sends" into a void is worse than one that refuses to,
    | because nobody finds out for a week.
    */
    'provider' => env('MARKETING_PROVIDER'),

    'cakemail' => [
        'key' => env('CAKEMAIL_API_KEY'),
        'account_id' => env('CAKEMAIL_ACCOUNT_ID'),
        'base_url' => env('CAKEMAIL_BASE_URL', 'https://api.cakemail.dev'),
    ],

    /*
    | Where the sending address has got to.
    |
    | Nothing may go out to a client list until this reads `verified`: an
    | unverified sender is how a business's whole domain ends up in spam
    | folders it cannot get out of.
    */
    'sender_statuses' => ['not_configured', 'verification_required', 'pending', 'verified', 'failed'],

    /*
    | The starting points offered when a campaign is created.
    |
    | Written for spas and salons rather than as generic newsletter shells,
    | because the point of a template is that most of the thinking is already
    | done by the time somebody opens it.
    */
    'templates' => [
        'promotion' => ['label' => 'General promotion'],
        'special_offer' => ['label' => 'Special offer'],
        'we_miss_you' => ['label' => 'We miss you'],
        'birthday' => ['label' => 'Birthday offer'],
        'new_service' => ['label' => 'New service'],
        'new_staff' => ['label' => 'New staff member'],
        'holiday' => ['label' => 'Holiday promotion'],
        'seasonal' => ['label' => 'Seasonal promotion'],
        'event' => ['label' => 'Event announcement'],
        'availability' => ['label' => 'Appointment availability'],
        'gift_card' => ['label' => 'Gift card promotion'],
        'membership' => ['label' => 'Membership promotion'],
    ],

];
