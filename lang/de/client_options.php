<?php

declare(strict_types=1);

/*
| Die Katalogbezeichnungen des Kundenmoduls.
|
| Die Schlüssel sind der in der Datenbank gespeicherte Wert. config/clients.php
| bleibt damit die eine Liste, die Auswahlfeld, Validierungsregel und
| Kundenmodul gemeinsam lesen, während jede Sprache nur entscheidet, wie die
| Optionen heißen.
|
| Jeder englische Wert hier ist die Bezeichnung, die config/clients.php ohnehin
| schon trug.
*/

return [
    'fields' => [
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'mobile' => 'Mobilnummer',
        'email' => 'E-Mail-Adresse',
        'date_of_birth' => 'Geburtsdatum',
        'gender' => 'Geschlecht',
        'address' => 'Adresse',
        'city' => 'Stadt',
        'state' => 'Bundesland / Region',
        'postal_code' => 'Postleitzahl',
        'country' => 'Land',
        'preferred_location' => 'Bevorzugter Standort',
        'preferred_staff' => 'Bevorzugte Fachkraft',
        'avatar' => 'Profilbild',
        'notes' => 'Notizen',
    ],

    'name_formats' => [
        'first_last' => 'Vorname, dann Nachname',
        'last_first' => 'Nachname, dann Vorname',
        'first_initial' => 'Vorname und erster Buchstabe des Nachnamens',
        'preferred_last' => 'Rufname, dann Nachname',
    ],

    'phone_types' => [
        'mobile' => 'Mobil',
        'home' => 'Privat',
        'work' => 'Geschäftlich',
        'other' => 'Sonstiges',
    ],

    'email_types' => [
        'personal' => 'Privat',
        'work' => 'Geschäftlich',
        'other' => 'Sonstiges',
    ],

    'statuses' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'archived' => 'Archiviert',
    ],

    'default_statuses' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'communication_methods' => [
        'email' => 'E-Mail',
        'sms' => 'SMS',
        'phone' => 'Telefon',
        'none' => 'Keine Präferenz',
    ],

    'marketing_defaults' => [
        'ask' => 'Kundin oder Kunden fragen',
        'in' => 'Zugestimmt',
        'out' => 'Abgelehnt',
    ],

    'duplicate_rules' => [
        'email' => 'Gleiche E-Mail-Adresse',
        'mobile' => 'Gleiche Mobilnummer',
        'name_mobile' => 'Gleicher Vorname, Nachname und gleiche Mobilnummer',
    ],

    'search_fields' => [
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'mobile' => 'Mobilnummer',
        'email' => 'E-Mail-Adresse',
        'client_id' => 'Kundennummer',
    ],

    'creation_sources' => [
        'client_list' => 'Kundenliste',
        'booking' => 'Buchungsansicht',
        'calendar' => 'Kalender',
        'walk_in' => 'Laufkundschaft',
        'pos' => 'Kasse',
    ],

    'booking_panels' => [
        'preferred_staff' => 'Bevorzugte Fachkraft',
        'preferred_location' => 'Bevorzugter Standort',
        'preferences' => 'Kundenpräferenzen',
        'notes' => 'Wichtige Notizen',
        'last_booking' => 'Letzte Buchung',
        'recent_visits' => 'Letzte Besuche',
        'recent_staff' => 'Zuletzt besuchte Fachkräfte',
        'rating' => 'Durchschnittliche Kundenbewertung',
        'book_same_again' => 'Dasselbe erneut buchen',
    ],

    'history_panels' => [
        'upcoming' => 'Anstehende Termine',
        'previous' => 'Vergangene Termine',
        'cancelled' => 'Abgesagte Termine',
        'no_shows' => 'Nicht erschienen',
        'services' => 'Bereits gebuchte Leistungen',
        'staff' => 'Bereits gebuchte Fachkräfte',
        'locations' => 'Besuchte Standorte',
        'notes' => 'Kundennotizen',
        'preferences' => 'Präferenzen',
        'activity' => 'Buchungsaktivität',
    ],

    'tag_colors' => [
        'slate' => 'Schiefer',
        'violet' => 'Violett',
        'blue' => 'Blau',
        'teal' => 'Petrol',
        'green' => 'Grün',
        'amber' => 'Bernstein',
        'rose' => 'Rosé',
        'plum' => 'Pflaume',
    ],
];
