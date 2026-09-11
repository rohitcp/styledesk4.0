<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
|
| The diary drawn against the clock: who is working, who is booked, and where
| the gaps are.
|
*/

return [
    'title' => 'Kalender',
    'subtitle' => 'Wer arbeitet, wer gebucht ist und wo Lücken sind.',

    'nav' => [
        'previous' => 'Vorheriger Tag',
        'next' => 'Nächster Tag',
        'today' => 'Heute',
        'pick' => 'Datum wählen',
    ],

    'views' => [
        'day' => 'Tag',
        'week' => 'Woche',
        'month' => 'Monat',
    ],

    'filters' => [
        'location' => 'Standort',
        'staff' => 'Team',
        'resource' => 'Ressource',
        'all_staff' => 'Gesamtes Team',
        'all_resources' => 'Alle Ressourcen',
        'all_locations' => 'Alle Standorte',
        'interval' => 'Intervall',
        'minutes' => ':count Min.',
        'service' => 'Leistung',
        'all_services' => 'Alle Leistungen',
        'search' => 'Suchen…',
    ],

    'summary' => [
        'total' => 'Buchungen',
        'arrived' => 'Eingecheckt',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
        'no_show' => 'Nicht erschienen',
        'owing' => 'Offener Betrag',
        'revenue' => 'Geplanter Umsatz',
    ],

    'card' => [
        'balance' => 'Offener Betrag · :amount',
        'membership' => 'Mitgliedschaft',
        /* Reserved until the client walks in — the rule the redemption engine
           keeps. A calendar that called a future appointment "used" would be
           telling the client they had already had it. */
        'credits_reserved' => 'Mitgliedschaft · :count Guthaben reserviert|Mitgliedschaft · :count Guthaben reserviert',
        'credits_used' => 'Mitgliedschaft · :count Guthaben genutzt|Mitgliedschaft · :count Guthaben genutzt',
        'note' => 'Enthält eine Notiz',
    ],

    'more' => '+:count weitere',
    'preview' => [
        'status' => 'Status',
        'payment' => 'Zahlung',
    ],

    'now' => 'Jetzt · :time',
    'break' => ':minutes Min. Pause',
    'off' => 'Arbeitet nicht',
    'closed' => 'An diesem Tag geschlossen.',
    'no_staff' => 'An diesem Standort ist noch niemand für Leistungen eingerichtet.',
    'empty' => 'An diesem Tag ist nichts gebucht.',
    'loading' => 'Tag wird geladen…',
    'new_booking' => '+ Neue Buchung',
    'free_slot' => ':staff um :time buchen',
];
