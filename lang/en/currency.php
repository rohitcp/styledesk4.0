<?php

declare(strict_types=1);

/*
| The Currency module.
|
| Currency *names* are not here: they live in config/currencies.php alongside
| the symbol and the formatting rules, because a currency's name is part of
| its definition rather than a piece of interface copy. "US Dollar" is what
| that currency is called; translating it per language would make the code and
| the name disagree about which money is meant.
*/

return [
    'title' => 'Currency',
    'intro' => 'The currency your prices are set in, and any additional currencies you also price in.',
    'edit' => 'Edit currencies',
    'saved' => 'Currency settings updated successfully.',

    'primary' => 'Primary currency',

    'is_primary' => 'primary',
    'primary_hint' => 'The default for services, products, packages, memberships, deposits, fees, discounts, taxes, payments, refunds and reports.',
    'secondary' => 'Additional currencies',
    'secondary_hint' => 'Currencies you also price in. The primary is always available and is not listed here.',
    'enabled' => 'Enabled currencies',
    'format' => 'How prices are shown',
    'format_hint' => 'Set by the currency, not by you — the symbol, its position, the separators and the number of decimal places.',

    'single_currency' => 'You price in one currency. Add another and pricing fields will ask for a price in each.',

    'no_conversion' => 'Prices are not converted between currencies. You set the price in each one yourself, so a rate moving overnight never changes what a client was quoted.',
    'scope_note' => 'Changing your primary currency does not re-price anything. Existing prices keep the currency they were entered in.',

    'validation' => [
        'primary_required' => 'Choose a primary currency.',
        'unsupported' => 'That currency is not available.',
    ],
];
