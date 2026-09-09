<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
|
| The bookable work a business sells. This file decides which options exist
| and where the limits are; the labels come from the services language file,
| so a business running in Spanish gets Spanish without a second list to keep
| in step.
|
| Named service_options rather than services because config/services.php is
| Laravel's own file for third-party credentials, and a domain config sharing
| that name would be read by whatever goes looking for a mail driver.
|
*/

return [

    /*
    | How long a service may run.
    |
    | Eight hours is a full day, which is the longest thing anybody books as
    | one service. The cap exists so a mistyped "600" is refused at the form
    | rather than drawn across a week of calendar.
    */
    'max_duration_minutes' => 480,

    /* The ancillary periods: prep, processing, cleanup and buffer. Shorter
       than a service, because none of them is the work itself. */
    'max_ancillary_minutes' => 240,

    /*
    | The calendar colours a service may take.
    |
    | Moved to config/colors.php, which resources share: a service block and
    | the room it occupies appear on the same screen, and two palettes would
    | eventually disagree in a way only a reader could see.
    */

    /* What a service costs the calendar when nobody says otherwise. */
    'default_duration_minutes' => 60,
];
