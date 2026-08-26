<?php

/*
|--------------------------------------------------------------------------
| Default Service Categories
|--------------------------------------------------------------------------
|
| Copied into every new tenant as its own rows. From that moment they are
| ordinary tenant categories: renaming or deactivating one affects nobody else,
| which is the point of seeding rather than sharing a global table.
|
| Order here becomes display_order, so the list arrives in a sensible shape
| before anyone reorders it.
|
*/

return [
    'Hair', 'Hair Color', 'Hair Treatment', 'Styling', 'Barber',
    'Nails', 'Manicure & Pedicure',
    'Facial', 'Skincare', 'Makeup',
    'Waxing', 'Threading', 'Eyebrows & Lashes',
    'Massage', 'Body Treatment', 'Spa', 'Sauna & Steam',
    'Wellness', 'Acupuncture', 'Chiropractic', 'Physiotherapy',
    'Laser Treatment', 'Hair Removal',
    'Tattoo', 'Piercing',
    'Bridal Services', "Men's Grooming", 'Kids Services',
    'Packages', 'Membership Services', 'Consultation', 'Add-On Services',
    'Other',
];
