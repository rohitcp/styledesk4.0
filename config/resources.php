<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Resources
|--------------------------------------------------------------------------
|
| The bookable things that are not people. This file decides which options
| exist; the labels come from the resources language file, so a business
| running in Spanish gets Spanish without a second list to keep in step.
|
*/

return [

    /*
     * The utilization a business is aiming for, as a percentage.
     *
     * Not a rule and not a limit — nothing is refused for missing it. It is
     * the number the summary panel congratulates, so an owner who has got
     * their rooms working sees that they have rather than having to compare
     * two figures themselves.
     *
     * 75% because a room booked much beyond that has no slack left for a
     * client running late or a treatment overrunning, and a salon at 100%
     * is one turning people away.
     */
    'utilization_target' => 75,

    /*
    | How resources are numbered when a business has not said otherwise.
    |
    | The code is generated rather than typed: "RES-001" after "RES-002" is
    | the kind of mistake nobody notices until two labels on two chairs say
    | the same thing. A business changes the prefix and the width in App
    | Settings; the number is always the next one it has not used.
    |
    | `max_attempts` bounds the search for a free number, so a business whose
    | codes were all typed by hand cannot turn one save into an endless loop.
    */
    'code' => [
        'prefix' => 'RES-',
        'padding' => 3,
        'max_prefix_length' => 12,
        'min_padding' => 1,
        'max_padding' => 6,
        'max_attempts' => 1000,
    ],

    /*
    | What a new business starts with.
    |
    | Seeded rather than left blank, because "styling chair" and "treatment
    | room" are the same idea in every salon and typing them out is not the
    | work anyone signed up for. A business deactivates the ones it does not
    | use — never deletes them, because a default that can be removed is one
    | it has no way to get back.
    |
    | `group` is the heading the category sits under in a list thirty entries
    | long; `capacity` is what a resource in that category starts with, and a
    | couples room is the one place two clients share.
    */
    'seed_categories' => [
        /* chairs */
        ['key' => 'styling-chair', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'barber-chair', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'shampoo-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'hair-processing-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'nail-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'manicure-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'pedicure-chair', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'makeup-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'lash-brow-station', 'group' => 'chairs', 'capacity' => 1],
        ['key' => 'trolley-mobile-station', 'group' => 'chairs', 'capacity' => 1],

        /* rooms */
        ['key' => 'facial-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'treatment-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'massage-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'couples-massage-room', 'group' => 'rooms', 'capacity' => 2],
        ['key' => 'waxing-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'spa-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'consultation-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'private-room', 'group' => 'rooms', 'capacity' => 1],
        ['key' => 'multi-purpose-room', 'group' => 'rooms', 'capacity' => 1],

        /* wellness */
        ['key' => 'sauna', 'group' => 'wellness', 'capacity' => 4],
        ['key' => 'steam-room', 'group' => 'wellness', 'capacity' => 4],
        ['key' => 'shower-room', 'group' => 'wellness', 'capacity' => 1],

        /* beds */
        ['key' => 'treatment-bed', 'group' => 'beds', 'capacity' => 1],
        ['key' => 'massage-table', 'group' => 'beds', 'capacity' => 1],
        ['key' => 'facial-bed', 'group' => 'beds', 'capacity' => 1],

        /* equipment */
        ['key' => 'equipment', 'group' => 'equipment', 'capacity' => 1],
        ['key' => 'portable-equipment', 'group' => 'equipment', 'capacity' => 1],
        ['key' => 'shared-equipment', 'group' => 'equipment', 'capacity' => 1],

        /* general */
        ['key' => 'reception-waiting-area', 'group' => 'general', 'capacity' => 1],
        ['key' => 'other', 'group' => 'general', 'capacity' => 1],

    ],

    /*
    | Why a resource is unavailable.
    |
    | A reason rather than a free note, because the calendar shows it and a
    | column of prose cannot be read at a glance — and because "how much time
    | did we lose to repairs" is a question a business will eventually ask.
    */
    'block_reasons' => ['maintenance', 'cleaning', 'repair', 'closure', 'other'],

    /*
    | The state a resource is in, set by hand.
    |
    | Distinct from a block, which is a period with a reason and an end. This
    | is "the room is out at the moment" without anyone committing to when it
    | comes back.
    */
    'availability_statuses' => ['available', 'unavailable', 'maintenance'],

    /* Whose opening hours a resource keeps. */
    'availability_types' => ['location', 'custom'],

    /*
    | The steps a resource may be booked in, and the periods either side.
    |
    | A fixed list rather than a free number: a diary drawn in seven-minute
    | steps is a diary nobody can read, and every one of these divides an
    | hour.
    */
    'intervals' => [5, 10, 15, 20, 30, 45, 60],
    'ancillary_minutes' => [0, 5, 10, 15, 20, 30, 45, 60],

    /* What a resource holds when nobody says otherwise. */
    'default_capacity' => 1,

    /* The largest capacity a single resource may claim. */
    'max_capacity' => 50,
];
