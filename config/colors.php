<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Colour palette
|--------------------------------------------------------------------------
|
| The colours anything in StyleDesk can be drawn in: a service on the
| calendar, a resource in the availability view. One list rather than one per
| module, because the two appear on the same screen — a service block and the
| room it occupies — and two palettes would eventually disagree in a way only
| a reader could see.
|
| A fixed set rather than a free colour picker for the first choice: the
| calendar has to stay readable with forty things on it, and a business given
| a full spectrum will choose two yellows that differ only up close. The last
| card in the picker opens the browser's own picker for the businesses that
| need a colour of their own.
|
*/

return [

    'palette' => [
        '#3d348b', '#0d9488', '#b45309', '#b91c1c',
        '#6d28d9', '#0e7490', '#9d174d', '#4d7c0f',
    ],

];
