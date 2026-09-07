<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Customer reviews
|--------------------------------------------------------------------------
|
| One source for the vocabulary of the review module: how long a business may
| wait before asking, which ways it may ask, where a piece of feedback can get
| to, and which ratings count as a problem. The settings screen builds its
| lists from these, the controller validates against them and the badges read
| their colours from them — three readings of one list rather than three lists.
|
*/

return [

    /*
    | How long after the appointment the request goes out.
    |
    | Minutes, except `next_day`, which is not a duration: it is the next
    | morning, and a business that picks it means "not tonight" rather than
    | "in fourteen hours". `App\Support\ReviewRequests::dueAt()` reads it.
    */
    'delays' => [
        'immediate' => ['minutes' => 0],
        '1h' => ['minutes' => 60],
        '3h' => ['minutes' => 180],
        '6h' => ['minutes' => 360],
        'next_day' => ['minutes' => null, 'next_morning_hour' => 10],
    ],

    /*
    | Which ways a business may ask.
    |
    | `available` false renders the option disabled and labelled "Coming
    | soon", the same way config/notifications.php treats a channel nothing
    | can deliver yet. Present, so the screen tells the truth about what is
    | planned; inert, so nobody switches on a message that never arrives.
    */
    'channels' => [
        'email' => ['available' => true],
        'sms' => ['available' => false],
        'both' => ['available' => false],
    ],

    /*
    | Where a piece of feedback can get to.
    |
    | Every review carries one, including the five-star ones — a single list
    | that can be filtered beats two lists that drift. A happy review simply
    | starts and stays at `new` unless somebody picks it up.
    */
    'statuses' => [
        'new' => ['class' => 'styledesk_badge--info'],
        'reviewing' => ['class' => 'styledesk_badge--setup'],
        'contacted' => ['class' => 'styledesk_badge--soon'],
        'resolved' => ['class' => 'styledesk_badge--active'],
        'closed' => ['class' => 'styledesk_badge--soon'],
        'no_action' => ['class' => 'styledesk_badge--soon'],
    ],

    /*
    | The line between the two journeys.
    |
    | Four and up is shown the Google button; three and below is not, and is
    | asked instead how the business could have done better. Named here rather
    | than written as a `>= 4` in three files, because it is a judgement about
    | the product and it is the one thing in this module a business might one
    | day want to move.
    */
    'positive_from' => 4,

    /*
    | What the stars mean, for the labels beside them.
    */
    'ratings' => [1, 2, 3, 4, 5],

    /*
    | Optional detail categories a business can turn on. Off in MVP; the list
    | is here so the settings screen has something to offer rather than an
    | empty section that reads as a bug.
    */
    'categories' => [
        'service_quality',
        'staff_experience',
        'cleanliness',
        'atmosphere',
        'value',
    ],

];
