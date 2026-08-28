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
    | What a new business starts with.
    |
    | Seeded rather than left blank, because "styling chair" and "treatment
    | room" are the same idea in every salon and typing them out is not the
    | work anyone signed up for. A business deletes what it does not use.
    |
    | `capacity` is what a resource in that category starts with: a couples
    | room is the one place two clients share, and it is the exception.
    */
    'seed_categories' => [
        ['key' => 'styling-chair', 'capacity' => 1],
        ['key' => 'barber-chair', 'capacity' => 1],
        ['key' => 'shampoo-station', 'capacity' => 1],
        ['key' => 'treatment-room', 'capacity' => 1],
        ['key' => 'massage-room', 'capacity' => 1],
        ['key' => 'couples-massage-room', 'capacity' => 2],
        ['key' => 'facial-room', 'capacity' => 1],
        ['key' => 'nail-station', 'capacity' => 1],
        ['key' => 'pedicure-chair', 'capacity' => 1],
        ['key' => 'sauna', 'capacity' => 4],
        ['key' => 'equipment', 'capacity' => 1],
    ],

    /*
    | Why a resource is unavailable.
    |
    | A reason rather than a free note, because the calendar shows it and a
    | column of prose cannot be read at a glance — and because "how much time
    | did we lose to repairs" is a question a business will eventually ask.
    */
    'block_reasons' => ['maintenance', 'cleaning', 'repair', 'closure', 'other'],

    /* What a resource holds when nobody says otherwise. */
    'default_capacity' => 1,

    /* The largest capacity a single resource may claim. */
    'max_capacity' => 50,
];
