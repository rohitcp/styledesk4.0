<?php

declare(strict_types=1);

/* The Languages settings module, and the header selector. */

return [
    'title' => 'Languages',
    'description' => 'Set the primary application language and choose the additional languages available to your team.',

    'primary' => 'Primary language',

    'is_primary' => 'primary',
    'primary_hint' => 'What the app is shown in by default. Everyone can change their own from the header once more than one language is enabled.',
    'secondary' => 'Additional languages',
    'secondary_hint' => 'Languages your team can choose from. The primary language is always available and is not listed here.',
    'enabled' => 'Enabled languages',

    'edit' => 'Edit languages',
    'saved' => 'Language settings updated successfully.',
    'preference_saved' => 'Language changed.',

    'selector_label' => 'Application language',
    'your_language' => 'Your language',
    'your_language_hint' => 'Only changes what you see. Your colleagues keep their own.',

    'scope_note' => 'This changes the StyleDesk interface only. Your service names, client records, email templates and anything else your business has typed stay exactly as they are.',

    'single_language' => 'Your team uses one language. Add another to let people choose their own from the header.',
    'primary_required' => 'Choose a primary language.',
    'unsupported' => 'That language is not available yet.',
    'partial' => 'partly translated',
    'partial_hint' => 'Languages marked “partly translated” are not finished. Anything not yet translated is shown in English.',
];
