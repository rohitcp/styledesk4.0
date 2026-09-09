<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Access codes
    |--------------------------------------------------------------------------
    |
    | StyleDesk is not open to the public yet. Before the sign-in and sign-up
    | screens can be reached at all, a visitor has to enter one of these codes
    | — they are handed out to the people invited to try the product, and the
    | list is what closes the door on everybody else.
    |
    | Comma separated in the environment so the list can be changed, or a code
    | withdrawn, without a deploy. Whitespace around each entry is ignored, so
    | a copied-and-pasted code with a stray space still works.
    |
    */

    'codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ACCESS_GATE_CODES', '1000,2000,3000,4000,5000'))
    ), fn (string $code): bool => $code !== '')),

];
