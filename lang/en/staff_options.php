<?php

declare(strict_types=1);

/*
| The Staff module's option lists.
|
| Keyed by the value stored in the database, so the dropdown and the validation
| rule keep reading one list from config/staff.php while each language decides
| what the options are called.
|
| Pronouns are the exception worth noting: "she/her" is not a phrase to
| translate word for word, it is how a person refers to themselves, so each
| language states its own set rather than mirroring English grammar.
*/

return [
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

    'provider_types' => [
        'service-provider' => 'Service provider',
        'non-provider' => 'Non-service provider',
        'manager-provider' => 'Manager + service provider',
        'front-desk' => 'Reception / front desk',
        'administrative' => 'Administrative staff',
        'support' => 'Support staff',
    ],

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

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending-invite' => 'Pending invite',
        'invite-queued' => 'Invite queued',
        'invite-failed' => 'Invite failed',
        'invite-expired' => 'Invite expired',
        'suspended' => 'Suspended',
        'archived' => 'Archived',
    ],

    'sorts' => [
        'name' => 'Name',
        'recent' => 'Recently added',
        'role' => 'Role',
        'location' => 'Location',
        'status' => 'Status',
    ],
];
