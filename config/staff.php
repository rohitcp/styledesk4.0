<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Staff options
|--------------------------------------------------------------------------
|
| The lists the staff directory filters on and the staff form offers, from
| §5, §6 and §16. Kept here so the filter dropdown and the display map read
| from one source — a filter offering a value the display cannot label shows
| a blank row, and the reverse shows a record nothing can find.
|
*/

return [

    /*
    | §6. What the business pays someone as, which is not what the
    | application lets them do — that is their role.
    */
    'employment_types' => [
        'full-time' => 'Full-time employee',
        'part-time' => 'Part-time employee',
        'contractor' => 'Contractor',
        'independent' => 'Independent provider',
        'commission' => 'Commission-based',
        'booth-renter' => 'Booth / chair renter',
        'freelancer' => 'Freelancer',
        'temporary' => 'Temporary staff',
        'apprentice' => 'Apprentice / trainee',
        'intern' => 'Intern',
        'volunteer' => 'Volunteer',
        'other' => 'Other',
    ],

    /*
    | §6. Whether and how this person appears on a booking calendar.
    */
    'provider_types' => [
        'service-provider' => 'Service provider',
        'non-provider' => 'Non-service provider',
        'manager-provider' => 'Manager + service provider',
        'front-desk' => 'Reception / front desk',
        'administrative' => 'Administrative staff',
        'support' => 'Support staff',
    ],

    /*
    | §6. Optional, and more than one may apply.
    */
    'specialities' => [
        'hair-stylist' => 'Hair stylist',
        'barber' => 'Barber',
        'colourist' => 'Colourist',
        'nail-technician' => 'Nail technician',
        'massage-therapist' => 'Massage therapist',
        'esthetician' => 'Esthetician',
        'makeup-artist' => 'Makeup artist',
        'lash-technician' => 'Lash technician',
        'brow-specialist' => 'Brow specialist',
        'therapist' => 'Therapist',
        'consultant' => 'Consultant',
    ],

    /*
    | Offered as a list rather than a free-text box so the same person is not
    | recorded as "she/her", "She/Her" and "shehers" in three places — but the
    | list is not the whole world, which is why the field stays optional and
    | "Prefer not to say" is a real answer rather than an empty one.
    */
    'pronouns' => [
        'she/her' => 'she/her',
        'he/him' => 'he/him',
        'they/them' => 'they/them',
        'she/they' => 'she/they',
        'he/they' => 'he/they',
        'prefer-not-to-say' => 'Prefer not to say',
    ],

    'phone_types' => [
        'mobile' => 'Mobile',
        'work' => 'Work',
        'home' => 'Home',
        'other' => 'Other',
    ],

    /*
    | §3's status list. `class` is the badge style; the directory derives the
    | status rather than storing it, because several of these are facts about
    | other records — an invitation's state, an archive timestamp — and a
    | stored copy would be a second answer able to disagree with the first.
    */
    'statuses' => [
        'active' => ['label' => 'Active', 'class' => 'styledesk_badge--active'],
        'inactive' => ['label' => 'Inactive', 'class' => 'styledesk_badge--soon'],
        // Away and coming back. Its own badge because a rota has to tell it
        // apart from someone who has left.
        'on-leave' => ['label' => 'On Leave', 'class' => 'styledesk_badge--setup'],
        'pending-invite' => ['label' => 'Pending invite', 'class' => 'styledesk_badge--setup'],
        // Queued but not yet handed to the mail provider. Almost always means
        // no queue worker is running.
        'invite-queued' => ['label' => 'Invite queued', 'class' => 'styledesk_badge--setup'],
        'invite-failed' => ['label' => 'Invite failed', 'class' => 'styledesk_badge--soon'],
        'invite-expired' => ['label' => 'Invite expired', 'class' => 'styledesk_badge--setup'],
        'suspended' => ['label' => 'Suspended', 'class' => 'styledesk_badge--soon'],
        'archived' => ['label' => 'Archived', 'class' => 'styledesk_badge--soon'],
    ],

    /*
    | The staff ID.
    |
    | Handed out by the application rather than typed, for the reason a client
    | reference is: an identifier somebody invents is one two people invent
    | differently — EMP-7, emp007, 7 — and the column stops being something
    | anybody can search or sort by. Configurable because a business arriving
    | with its own numbering will want its own prefix.
    */
    'staff_id' => [
        'prefix' => env('STAFF_ID_PREFIX', 'EMP-'),
        'padding' => (int) env('STAFF_ID_PADDING', 4),
    ],

    'sorts' => [
        'name' => 'Name',
        'recent' => 'Recently added',
        'role' => 'Role',
        'location' => 'Location',
        'status' => 'Status',
    ],

    /*
    | The share of a bookable day a business is aiming to have booked.
    |
    | A celebration threshold and a bar on the utilization board, never a rule:
    | nothing is refused for missing it and nobody is flagged for it. It exists
    | so an owner who has got their team working sees that they have, rather
    | than comparing two figures themselves.
    |
    | 75% because a day booked much past that has no slack left for a client
    | running late or a treatment overrunning, and a stylist at 100% is one
    | who has not stopped since nine.
    */
    'utilization_target' => 75,

    /*
    | The shortest hole in a day worth calling an opening.
    |
    | Below this it is arithmetic rather than an opportunity — a nine-minute
    | gap between two appointments cannot be sold, and listing it teaches
    | whoever reads the list to stop reading it.
    */
    'utilization_gap_minutes' => 30,
];
