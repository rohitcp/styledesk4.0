<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Behavioural tags
|--------------------------------------------------------------------------
|
| Tags StyleDesk works out for itself from what a client does, as opposed to
| the ones a receptionist puts on by hand. The two are kept apart everywhere
| they appear: "VIP" is a judgement someone made, "Frequent booker" is a
| count, and a screen that mixed them would make the first look as reliable
| as the second.
|
| Defined here rather than per business, and not editable in the app: the key
| is what reporting and future automation will join on, and a business that
| renamed "no_show_risk" would break every rule that referred to it. What a
| business does control is whether each one is applied at all.
|
| `rule` is the plain-English statement of what earns the tag. It is shown to
| the reader as-is — a threshold nobody can see is a threshold nobody can
| trust — and it is deliberately prose rather than a machine-readable rule:
| the engine that evaluates these arrives with bookings, and inventing its
| syntax now would be guessing at an interface nothing implements.
|
*/

return [

    'categories' => [
        'frequency' => 'Booking frequency',
        'lifecycle' => 'Client lifecycle',
        'spending' => 'Spending',
        'attendance' => 'Attendance',
        'timing' => 'Booking timing',
        'preference' => 'Staff & location preference',
        'rebooking' => 'Rebooking',
        'marketing' => 'Marketing',
        'membership' => 'Membership & packages',
        'service' => 'Service behaviour',
        'engagement' => 'Engagement',
        'communication' => 'Communication',
        'channel' => 'Booking channel',
    ],

    'tags' => [
        // ------------------------------------------------- booking frequency
        'frequent_booker' => ['label' => 'Frequent booker', 'category' => 'frequency', 'rule' => 'Six or more completed bookings in the last six months.'],
        'regular_booker' => ['label' => 'Regular booker', 'category' => 'frequency', 'rule' => 'Three to five completed bookings in the last six months.'],
        'occasional_booker' => ['label' => 'Occasional booker', 'category' => 'frequency', 'rule' => 'One or two completed bookings in the last six months.'],

        // -------------------------------------------------- client lifecycle
        'first_time_client' => ['label' => 'First-time client', 'category' => 'lifecycle', 'rule' => 'One completed booking, and no earlier visit.'],
        'returning_client' => ['label' => 'Returning client', 'category' => 'lifecycle', 'rule' => 'Two or more completed bookings.'],
        'long_term_client' => ['label' => 'Long-term client', 'category' => 'lifecycle', 'rule' => 'First visit more than two years ago, and still booking.'],
        'overdue_for_visit' => ['label' => 'Overdue for visit', 'category' => 'lifecycle', 'rule' => 'Past their usual gap between visits by half again.'],
        'inactive_client' => ['label' => 'Inactive client', 'category' => 'lifecycle', 'rule' => 'No booking in the last twelve months.'],
        'recently_reactivated' => ['label' => 'Recently reactivated', 'category' => 'lifecycle', 'rule' => 'Booked again after twelve months or more away.'],

        // ------------------------------------------------------------ spending
        'high_value_client' => ['label' => 'High-value client', 'category' => 'spending', 'rule' => 'Lifetime spend in the top tenth of this business.'],
        'high_spend_client' => ['label' => 'High-spend client', 'category' => 'spending', 'rule' => 'Average visit value in the top fifth of this business.'],
        'low_spend_client' => ['label' => 'Low-spend client', 'category' => 'spending', 'rule' => 'Average visit value in the bottom fifth of this business.'],

        // ----------------------------------------------------------- attendance
        'frequent_no_show' => ['label' => 'Frequent no-show', 'category' => 'attendance', 'rule' => 'Three or more no-shows in the last twelve months.'],
        'no_show_risk' => ['label' => 'No-show risk', 'category' => 'attendance', 'rule' => 'Two or more no-shows in the last six months.'],
        'late_cancellation' => ['label' => 'Late cancellation', 'category' => 'attendance', 'rule' => 'Cancelled inside the notice period at least once in the last six months.'],
        'frequent_cancellation' => ['label' => 'Frequent cancellation', 'category' => 'attendance', 'rule' => 'Three or more cancellations in the last six months.'],
        'frequent_rescheduler' => ['label' => 'Frequent rescheduler', 'category' => 'attendance', 'rule' => 'Three or more bookings moved in the last six months.'],

        // -------------------------------------------------------- booking timing
        'last_minute_booker' => ['label' => 'Last-minute booker', 'category' => 'timing', 'rule' => 'Usually books within 24 hours of the appointment.'],
        'books_in_advance' => ['label' => 'Books in advance', 'category' => 'timing', 'rule' => 'Usually books two weeks or more ahead.'],
        'same_day_booker' => ['label' => 'Same-day booker', 'category' => 'timing', 'rule' => 'Has booked for the same day three or more times.'],
        'weekend_booker' => ['label' => 'Weekend booker', 'category' => 'timing', 'rule' => 'Most visits fall on a Saturday or Sunday.'],
        'weekday_booker' => ['label' => 'Weekday booker', 'category' => 'timing', 'rule' => 'Most visits fall Monday to Friday.'],
        'morning_booker' => ['label' => 'Morning booker', 'category' => 'timing', 'rule' => 'Most visits start before noon.'],
        'afternoon_booker' => ['label' => 'Afternoon booker', 'category' => 'timing', 'rule' => 'Most visits start between noon and five.'],
        'evening_booker' => ['label' => 'Evening booker', 'category' => 'timing', 'rule' => 'Most visits start after five.'],

        // ------------------------------------------- staff & location preference
        'prefers_same_staff' => ['label' => 'Prefers same staff', 'category' => 'preference', 'rule' => 'Four in five visits with the same team member.'],
        'prefers_same_location' => ['label' => 'Prefers same location', 'category' => 'preference', 'rule' => 'Four in five visits at the same location.'],
        'staff_flexible' => ['label' => 'Staff flexible', 'category' => 'preference', 'rule' => 'Seen by three or more team members in the last year.'],
        'location_flexible' => ['label' => 'Location flexible', 'category' => 'preference', 'rule' => 'Visited two or more locations in the last year.'],

        // ----------------------------------------------------------- rebooking
        'rebooking_client' => ['label' => 'Rebooking client', 'category' => 'rebooking', 'rule' => 'Usually books the next visit before leaving.'],
        'does_not_rebook' => ['label' => 'Does not rebook', 'category' => 'rebooking', 'rule' => 'Has not booked ahead at the end of a visit in the last year.'],

        // ----------------------------------------------------------- marketing
        'promotion_responsive' => ['label' => 'Promotion responsive', 'category' => 'marketing', 'rule' => 'Has booked from a promotion two or more times.'],
        'discount_sensitive' => ['label' => 'Discount sensitive', 'category' => 'marketing', 'rule' => 'Most visits were booked with a discount applied.'],
        'referral_client' => ['label' => 'Referral client', 'category' => 'marketing', 'rule' => 'Came to the business through a referral.'],
        'frequent_referrer' => ['label' => 'Frequent referrer', 'category' => 'marketing', 'rule' => 'Has referred three or more clients.'],

        // ------------------------------------------------ membership & packages
        'membership_user' => ['label' => 'Membership user', 'category' => 'membership', 'rule' => 'Holds an active membership.'],
        'package_user' => ['label' => 'Package user', 'category' => 'membership', 'rule' => 'Has an unspent package.'],

        // ------------------------------------------------------ service behaviour
        'frequent_add_on_buyer' => ['label' => 'Frequent add-on buyer', 'category' => 'service', 'rule' => 'Adds an extra to most visits.'],
        'multi_service_booker' => ['label' => 'Multi-service booker', 'category' => 'service', 'rule' => 'Usually books two or more services in one visit.'],
        'single_service_booker' => ['label' => 'Single-service booker', 'category' => 'service', 'rule' => 'Almost always books one service per visit.'],

        // ---------------------------------------------------------- engagement
        'high_engagement' => ['label' => 'High engagement', 'category' => 'engagement', 'rule' => 'Opens and acts on most messages sent to them.'],
        'low_engagement' => ['label' => 'Low engagement', 'category' => 'engagement', 'rule' => 'Rarely opens the messages sent to them.'],

        // ------------------------------------------------------- communication
        'sms_responsive' => ['label' => 'SMS responsive', 'category' => 'communication', 'rule' => 'Usually replies to SMS.'],
        'email_responsive' => ['label' => 'Email responsive', 'category' => 'communication', 'rule' => 'Usually replies to email.'],

        // ------------------------------------------------------ booking channel
        'online_booker' => ['label' => 'Online booker', 'category' => 'channel', 'rule' => 'Most bookings made through online booking.'],
        'phone_booker' => ['label' => 'Phone booker', 'category' => 'channel', 'rule' => 'Most bookings taken over the phone.'],
        'walk_in_client' => ['label' => 'Walk-in client', 'category' => 'channel', 'rule' => 'Most visits started as a walk-in.'],
    ],
];
