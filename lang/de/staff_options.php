<?php

declare(strict_types=1);

/*
| Die Auswahllisten des Personalmoduls.
|
| Die Schlüssel sind der in der Datenbank gespeicherte Wert. Auswahlfeld und
| Validierungsregel lesen deshalb weiterhin dieselbe Liste aus
| config/staff.php, während jede Sprache nur entscheidet, wie die Optionen
| heißen.
|
| Die Pronomen sind die erwähnenswerte Ausnahme: „she/her“ ist keine Wendung,
| die man Wort für Wort überträgt, sondern die Art, wie jemand über sich
| selbst spricht. Jede Sprache nennt deshalb ihren eigenen Satz, statt die
| englische Grammatik nachzubilden.
*/

return [
    'employment_types' => [
        'full-time' => 'Angestellt in Vollzeit',
        'part-time' => 'Angestellt in Teilzeit',
        'contractor' => 'Auftragnehmer',
        'independent' => 'Selbstständige Fachkraft',
        'commission' => 'Auf Provisionsbasis',
        'booth-renter' => 'Stuhlmiete',
        'freelancer' => 'Freiberuflich',
        'temporary' => 'Aushilfe',
        'apprentice' => 'Auszubildende:r',
        'intern' => 'Praktikant:in',
        'volunteer' => 'Ehrenamtlich',
        'other' => 'Sonstiges',
    ],

    'provider_types' => [
        'service-provider' => 'Fachkraft mit Behandlungen',
        'non-provider' => 'Ohne Behandlungen',
        'manager-provider' => 'Leitung mit Behandlungen',
        'front-desk' => 'Empfang',
        'administrative' => 'Verwaltung',
        'support' => 'Unterstützende Tätigkeit',
    ],

    'specialities' => [
        'hair-stylist' => 'Friseur:in',
        'barber' => 'Barbier',
        'colourist' => 'Coloristin/Colorist',
        'nail-technician' => 'Nageldesigner:in',
        'massage-therapist' => 'Masseur:in',
        'esthetician' => 'Kosmetiker:in',
        'makeup-artist' => 'Make-up-Artist',
        'lash-technician' => 'Wimpernstylist:in',
        'brow-specialist' => 'Brauenexpert:in',
        'therapist' => 'Therapeut:in',
        'consultant' => 'Berater:in',
    ],

    'pronouns' => [
        'she/her' => 'sie/ihr',
        'he/him' => 'er/ihm',
        'they/them' => 'they/them',
        'she/they' => 'sie/they',
        'he/they' => 'er/they',
        'prefer-not-to-say' => 'Keine Angabe',
    ],

    'phone_types' => [
        'mobile' => 'Mobil',
        'work' => 'Geschäftlich',
        'home' => 'Privat',
        'other' => 'Sonstiges',
    ],

    'statuses' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'on-leave' => 'Abwesend',
        'pending-invite' => 'Einladung offen',
        'invite-queued' => 'Einladung in Warteschlange',
        'invite-failed' => 'Einladung fehlgeschlagen',
        'invite-expired' => 'Einladung abgelaufen',
        'suspended' => 'Gesperrt',
        'archived' => 'Archiviert',
    ],

    'sorts' => [
        'name' => 'Name',
        'recent' => 'Zuletzt hinzugefügt',
        'role' => 'Rolle',
        'location' => 'Standort',
        'status' => 'Status',
    ],
];
