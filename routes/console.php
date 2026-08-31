<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Leads nobody has touched.
|
| Hourly rather than nightly: "no activity for 24 hours" read at 9am should
| mean 24 hours, not "since whenever the overnight run happened". The command
| is idempotent, so a missed hour costs nothing but the delay.
|
| Needs the scheduler running (`php artisan schedule:work`, or the usual
| cron entry in production). Without it nothing ages, and every lead stays
| where the booking screen left it — which is wrong, but quietly so.
*/
Schedule::command('bookings:age-leads')->hourly();
