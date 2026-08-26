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
        'pending-invite' => ['label' => 'Pending invite', 'class' => 'styledesk_badge--setup'],
        'invite-expired' => ['label' => 'Invite expired', 'class' => 'styledesk_badge--setup'],
        'suspended' => ['label' => 'Suspended', 'class' => 'styledesk_badge--soon'],
        'archived' => ['label' => 'Archived', 'class' => 'styledesk_badge--soon'],
    ],

    'sorts' => [
        'name' => 'Name',
        'recent' => 'Recently added',
        'role' => 'Role',
        'location' => 'Location',
        'status' => 'Status',
    ],
];
