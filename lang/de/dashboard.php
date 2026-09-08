<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Übersicht
|--------------------------------------------------------------------------
|
| Die Übersicht beantwortet je nach anmeldender Person eine andere Frage,
| deshalb ist die Sprache für die lesende Person geschrieben und nicht für die
| Daten darunter. Der Empfang fragt, wer am Tresen steht; die Inhaberin fragt,
| wie der Monat läuft.
|
*/

return [

    'title' => 'Übersicht',
    'greeting' => [
        'morning' => 'Guten Morgen',
        'afternoon' => 'Guten Tag',
        'evening' => 'Guten Abend',
    ],

    'location' => 'Standort',
    'all_locations' => 'Alle Standorte',

    'performance' => [
        'title' => 'Geschäftsentwicklung',
        'today' => 'Heute eingenommen',
        'month' => 'Diesen Monat eingenommen',
        'change' => 'ggü. denselben Tagen im Vormonat',
        'outstanding' => 'Offen',
        'bookings' => 'Buchungen diesen Monat',
        'average' => 'Durchschnittliche Buchung',
        /* Ausgesprochen statt als Null gezeigt: Ein erster Monat hat nichts
           zum Vergleichen, und „0 %“ läse sich wie ein Betrieb, der
           stillsteht. */
        'no_comparison' => 'Noch keine Zahlen für den Vormonat',
    ],

    'bookings_today' => [
        'title' => 'Buchungen heute',
        'total' => 'Gesamt',
        'pending_checkin' => 'Check-in offen',
        'checked_in' => 'Eingecheckt',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
        'no_show' => 'Nicht erschienen',
    ],

    'checkin' => [
        'title' => 'Check-in',
        'none' => 'Niemand wartet auf den Check-in.',
        'none_hint' => 'Termine erscheinen hier im Lauf des Tages.',
        'view_all' => 'Warteschlange öffnen',
        'action' => 'Einchecken',
    ],

    'arriving' => [
        'title' => 'Kommen bald',
        'none' => 'In der nächsten Stunde wird niemand erwartet.',
    ],

    'waiting' => [
        'title' => 'Wartend',
        'none' => 'Es wartet niemand.',
        'for' => 'Wartet :count Min.',
        'since' => 'Eingecheckt um :time',
        'too_long' => 'Wartet schon eine Weile',
    ],

    'staff_today' => [
        'title' => 'Heute im Dienst',
        'none' => 'Heute steht niemand im Dienstplan.',
        'shift' => 'Schicht',
        'now' => 'Bei einer Kundin oder einem Kunden',
        'next' => 'Als Nächstes',
        'remaining' => 'noch :count',
        'free' => 'Frei',
    ],

    'schedule_issues' => [
        'title' => 'Probleme im Dienstplan',
        'none' => 'Es gibt nichts, was Ihre Aufmerksamkeit braucht.',
        'working_without_a_shift' => 'Arbeitet ohne Schicht im Dienstplan',
        'bookings_without_staff' => 'Termine ohne zugewiesene Person',
    ],

    'clients' => [
        'title' => 'Kundschaft',
        'new_today' => 'Heute neu',
        'booked_today' => 'Heute erwartet',
        'returning_today' => 'Wiederkehrend',
        'active' => 'Aktive Kundschaft',
    ],

    'services' => [
        'title' => 'Diesen Monat am häufigsten gebucht',
        'none' => 'Diesen Monat wurde noch nichts gebucht.',
        'bookings' => ':count gebucht',
    ],

    'payments' => [
        'title' => 'Zahlungen',
        'collected' => 'Heute eingenommen',
        'outstanding' => 'Offen',
        'partial' => 'Teilweise bezahlt',
        'due_today' => 'Heute bedient und unbezahlt',
        'none' => 'Aus dem heutigen Tag ist nichts offen.',
    ],

    'alerts' => [
        'title' => 'Braucht Aufmerksamkeit',
        'none' => 'Es gibt nichts, was Ihre Aufmerksamkeit braucht.',
        'late' => ':count erwartete Person ist über ihrer Zeit|:count erwartete Personen sind über ihrer Zeit',
        'waiting_too_long' => ':count Person wartet schon eine Weile|:count Personen warten schon eine Weile',
        'unpaid' => ':count heute bedienter Termin ist nicht bezahlt|:count heute bediente Termine sind nicht bezahlt',
        'unstaffed' => ':count Termin heute hat niemanden zugewiesen|:count Termine heute haben niemanden zugewiesen',
    ],

    'my_next_client' => [
        'title' => 'Nächste Kundin, nächster Kunde',
        'none' => 'Sie haben heute keinen weiteren Termin.',
        'none_hint' => 'Was später heute noch gebucht wird, erscheint hier.',
        'here' => 'Jetzt da',
        'view_client' => 'Kundendatensatz ansehen',
        'view_booking' => 'Buchung ansehen',
    ],

    'my_day' => [
        'title' => 'Mein Tag',
        'none' => 'Für Sie ist heute nichts eingetragen.',
    ],

    'my_schedule' => [
        'title' => 'Meine Schicht',
        'none' => 'Sie stehen heute nicht im Dienstplan.',
        'from' => 'Von',
        'to' => 'Bis',
        'break' => 'Pause',
        'remaining' => 'Kommt noch',
    ],

    'my_performance' => [
        'title' => 'Mein Tag bisher',
        'clients' => 'Kundschaft',
        'completed' => 'Abgeschlossen',
        'average' => 'Durchschnittliche Leistung',
        'tips' => 'Trinkgeld',
        'minutes' => ':count Min.',
    ],

    'quick_actions' => [
        'title' => 'Schnellaktionen',
        'booking' => 'Buchung anlegen',
        'client' => 'Kundin oder Kunden anlegen',
        'checkin' => 'Jemanden einchecken',
        'staff' => 'Mitarbeitende anlegen',
        'service' => 'Leistung anlegen',
        'schedule' => 'Dienstplan verwalten',
        'calendar' => 'Kalender ansehen',
    ],

    /* Die Einrichtungsliste, sichtbar bis der letzte Punkt erledigt oder die
       Liste ausgeblendet ist. */
    'getting_started' => [
        'title' => 'Erste Schritte',
        'intro' => 'Ein paar Dinge sind noch einzurichten. Sie können jederzeit hierher zurückkommen.',
        'dismiss' => 'Ausblenden',

        /*
        | Die Liste selbst.
        |
        | Hier statt im DashboardController, wo es neun englische Literale
        | waren: Eine in PHP gebaute Liste ist trotzdem Text auf dem
        | Bildschirm — und wer auf Chinesisch las, bekam die ganze Liste auf
        | Englisch, während die Karte darum herum übersetzt war.
        */
        'items' => [
            'service' => 'Legen Sie Ihre erste Leistung an',
            'team' => 'Fügen Sie Teammitglieder hinzu',
            'schedules' => 'Richten Sie die Dienstpläne ein',
            'client' => 'Legen Sie Ihren ersten Kundendatensatz an',
            'online_booking' => 'Passen Sie die Onlinebuchung an',
            'payments' => 'Richten Sie die Zahlungen ein',
            'reminders' => 'Richten Sie Terminerinnerungen ein',
            'branding' => 'Fügen Sie Logo und Markenauftritt hinzu',
            'appointment' => 'Legen Sie Ihren ersten Termin an',
        ],
    ],
];
