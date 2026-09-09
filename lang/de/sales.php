<?php

declare(strict_types=1);

return [

    'title' => 'Umsatz',
    'intro' => 'Einnahmen, Zahlungen, offene Beträge, Erstattungen, Trinkgeld und Zahlungsvorgänge ansehen.',

    'period' => 'Zeitraum',
    'periods' => [
        'today' => 'Heute',
        'tomorrow' => 'Morgen',
        'yesterday' => 'Gestern',
        'last_3' => 'Letzte 3 Tage',
        'last_7' => 'Letzte 7 Tage',
        'week' => 'Diese Woche',
        'month' => 'Dieser Monat',
        'custom' => 'Eigener Zeitraum',
    ],
    'from' => 'Von',
    'to' => 'Bis',
    'apply' => 'Anwenden',

    'widgets' => [
        'total_sales' => 'Gesamtumsatz',
        'collected' => 'Eingenommene Zahlungen',
        'outstanding' => 'Offener Betrag',
        'refunds' => 'Erstattungen',
        'tips' => 'Eingenommenes Trinkgeld',
        'transactions' => 'Zahlungsvorgänge',
    ],

    'notes' => [
        'total_sales' => 'Nach Termindatum',
        'collected' => 'Nach Zahlungsdatum',
        'outstanding' => 'Noch offen',
        'refunds' => 'Nach Zahlungsdatum',
        'tips' => 'Nach Zahlungsdatum',
        'transactions' => 'Angenommene Zahlungen',
    ],

    'vs_previous' => 'ggü. vorigem Zeitraum',
    'show_these' => 'Diese anzeigen →',

    /*
    | Die beiden Hälften werden unterschiedlich gezählt, und die Seite sagt das.
    | Eine im August genommene Anzahlung für einen Termin im September ist der
    | Umsatz des September und die Zahlung des August; wer das nicht weiß, wird
    | feststellen, dass die Summen nicht aufgehen, und die Zahlen für falsch
    | halten.
    */
    'counting_note' => 'Umsätze und offene Beträge werden nach Termindatum gezählt. Zahlungen, Erstattungen und Trinkgeld danach, wann das Geld geflossen ist.',

    'transactions' => 'Zahlungsvorgänge',
    'search_placeholder' => 'Vorgang, Buchung, Kundschaft, E-Mail oder Telefon',
    'actions_for' => 'Aktionen für :name',
    'showing' => 'Angezeigt',
    'results' => [
        'zero' => 'Keine Vorgänge',
        'one' => '1 Vorgang',
        'many' => ':count Vorgänge',
        'clear' => 'Filter zurücksetzen',
    ],
    'empty' => 'In diesem Zeitraum ist kein Geld geflossen.',
    'walk_in' => 'Laufkundschaft',

    'columns' => [
        'reference' => 'Vorgang',
        'at' => 'Datum und Uhrzeit',
        'booking' => 'Buchung',
        'client' => 'Kundin/Kunde',
        'services' => 'Leistung',
        'staff' => 'Fachkraft',
        'location' => 'Standort',
        'total' => 'Buchungssumme',
        /* Diese Zahlung, im Unterschied zu dem, was die Buchung insgesamt
           eingebracht hat — zwei verschiedene Fragen, die Tabelle beantwortet
           beide. */
        'amount' => 'Bezahlt',
        'balance' => 'Saldo',
        'method' => 'Zahlungsart',
        'status' => 'Status',
    ],

    'drawer' => [
        'at_a_glance' => 'Auf einen Blick',
        'nothing_owed' => 'Nichts offen',
        'next_appointment' => 'Nächster Termin',
        'total_visits' => 'Besuche insgesamt',
        'lifetime_spend' => 'Gesamtumsatz',
        'last_visit' => 'Letzter Besuch',
        'tags' => 'Tags',
        'account' => 'Konto',
        'bookings' => 'Buchungen',
        'spend' => 'Insgesamt bezahlt',
        'owed' => 'Noch offen',
        'transaction' => 'Vorgang',
        'booking' => 'Buchung',
        'amount' => 'Diese Zahlung',
        'tip' => 'Trinkgeld',
        'paid_total' => 'Auf diese Buchung bezahlt',
        'recorded_by' => 'Angenommen von',
        'online' => 'Online',
        'processor_reference' => 'Referenz des Dienstleisters',
        'view_client' => 'Alle Details ansehen',
        'view_receipt' => 'Vollständigen Beleg ansehen',
        'download' => 'PDF herunterladen',
    ],

    'actions' => [
        'view_booking' => 'Buchung ansehen',
        'open_booking' => 'Buchungsseite öffnen',
        'view_client' => 'Kundendatensatz ansehen',
        'view_receipt' => 'Beleg ansehen',
    ],
];
