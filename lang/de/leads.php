<?php

declare(strict_types=1);

/*
| Buchungen, die begonnen und nicht zu Ende gebracht wurden.
|
| Die Sprache behandelt eine Anfrage als Rückruf, nicht als Misserfolg. Die
| meisten sind Menschen, die aufgelegt haben, um ein Datum zu prüfen — und ein
| Bildschirm, der sie „abgebrochen“ nennt, ließe den Empfang sich für den
| eigenen Kalender entschuldigen.
*/

return [

    'title' => 'Buchungsanfragen',
    'intro' => 'Buchungen, die begonnen, aber nie abgeschlossen wurden. Jede davon ist ein Rückruf, der sich lohnt.',

    'search' => 'Nach Name, Referenz oder Nummer suchen…',
    'search_label' => 'Anfragen durchsuchen',
    'all_statuses' => 'Alle Status',

    'actions' => [
        'complete' => 'Buchung fortsetzen',
        'view_booking' => 'Buchung ansehen',
        'view_client' => 'Kundendatensatz ansehen',
    ],

    'columns' => [
        'who' => 'Kundin/Kunde',
        'services' => 'Gewünscht',
        'expected' => 'Gewünscht für',
        'location' => 'Standort',
        'total' => 'Wert',
        'started' => 'Begonnen',
        'step' => 'Stehen geblieben bei',
        'status' => 'Status',
    ],

    /*
    | Was aus einer Anfrage geworden ist. Nicht, wie weit sie kam — das ist der
    | Schritt weiter unten, und beides bleibt bewusst getrennt: „Nachfassen
    | nötig“ sagt, dass man anrufen soll, „Anzahlung und Zahlung“ sagt, worüber.
    */
    'statuses' => [
        'draft' => ['label' => 'Entwurf'],
        'new' => ['label' => 'Neu'],
        'in-progress' => ['label' => 'In Bearbeitung'],
        'awaiting-confirmation' => ['label' => 'Wartet auf Kundschaft'],
        'awaiting-deposit' => ['label' => 'Wartet auf Anzahlung'],
        'payment-pending' => ['label' => 'Zahlung ausstehend'],
        'follow-up' => ['label' => 'Nachfassen nötig'],
        'contacted' => ['label' => 'Kontaktiert'],
        'converted' => ['label' => 'Umgewandelt'],
        'abandoned' => ['label' => 'Abgebrochen'],
        'cancelled' => ['label' => 'Storniert'],
        'lost' => ['label' => 'Verloren'],
        'expired' => ['label' => 'Abgelaufen'],
    ],

    /* Wie weit die Person im Buchungsablauf gekommen ist. */
    'steps' => [
        'service' => 'Leistung',
        'when' => 'Bei wem und wann',
        'details' => 'Buchungsdetails',
        'payment' => 'Anzahlung und Zahlung',
        'comms' => 'Kommunikation',
        'completed' => 'Abgeschlossen',
    ],

    /* Warum es dort endete. */
    'reasons' => [
        'changed-mind' => 'Hat es sich anders überlegt',
        'no-suitable-time' => 'Kein passender Termin',
        'staff-unavailable' => 'Bevorzugte Fachkraft nicht verfügbar',
        'price' => 'Preis',
        'duplicate' => 'Doppelte Anfrage',
        'declined' => 'Hat abgelehnt',
        'unreachable' => 'Nicht erreichbar',
        'booked-elsewhere' => 'Woanders gebucht',
        'no-availability' => 'Keine passende Verfügbarkeit',
        'other' => 'Sonstiges',
    ],

    'contact_methods' => [
        'phone' => 'Telefon',
        'sms' => 'SMS',
        'email' => 'E-Mail',
        'whatsapp' => 'WhatsApp',
        'in-person' => 'Persönlich',
    ],

    /*
    | Die Leiste, die die Liste über sich selbst öffnet.
    |
    | Ein Feld, das noch niemand ausgefüllt hat, sagt „Nicht angegeben“, statt
    | leer zurückzukommen: Eine leere Zeile liest sich wie ein Defekt, und was
    | fehlt, ist meist genau das, worum es im Gespräch geht.
    */
    'drawer' => [
        'not_selected' => 'Nicht angegeben',
        'summary' => 'Anfrage',
        'created' => 'Erstellt',
        'created_by' => 'Erstellt von',
        'last_activity' => 'Letzte Aktivität',
        'taken_by' => 'Zuletzt kontaktiert von',
        'client' => 'Kundin/Kunde',
        'name' => 'Name',
        'phone' => 'Telefon',
        'email' => 'E-Mail',
        'preferences' => 'Buchungspräferenzen',
        'booking' => 'Buchungsdetails',
        'services' => 'Leistungen',
        'duration' => 'Dauer',
        'price' => 'Preis',
        'date' => 'Datum',
        'time' => 'Uhrzeit',
        'staff' => 'Bei',
        'location' => 'Standort',
        'notes' => 'Notizen',
        'payment' => 'Zahlung',
        'estimated_total' => 'Geschätzter Gesamtbetrag',
        'deposit_required' => 'Anzahlung erforderlich',
        'deposit_paid' => 'Anzahlung geleistet',
        'outstanding' => 'Offen',
        'payment_status' => 'Zahlungsstatus',
        'journey' => 'Buchungsfortschritt',
        'activity' => 'Aktivität',
        'no_activity' => 'Noch nichts erfasst.',
        'stopped_at' => 'Stehen geblieben bei',
        'view_client' => 'Kundendatensatz öffnen',
        'close' => 'Schließen',
        'send_email' => 'E-Mail senden',
        'send_sms' => 'SMS senden',
        'soon' => 'Demnächst',
    ],

    'events' => [
        'created' => 'Buchungsanfrage angelegt',
        'step' => ':step abgeschlossen',
        'cancelled' => 'Storniert — :reason',
        'converted' => 'In eine Buchung umgewandelt',
        'client-created' => 'Client record created from walk-in details',
        'client-matched' => 'Matched to an existing client record',
        'contacted' => 'Kundschaft kontaktiert',
    ],

    'notes' => [
        'button' => 'Notizen',
        'title' => 'Notizen',
        'write' => 'Notiz hinzufügen',
        'placeholder' => 'Um 16 Uhr angerufen, niemand erreicht — morgen noch einmal.',
        'hint' => 'Wird im Kundendatensatz gespeichert und mit dieser Anfrage verknüpft, damit die nächste Person sie von beiden Seiten sieht.',
        'save' => 'Notiz speichern',
        'saved' => 'Notiz im Kundendatensatz gespeichert.',
        'empty' => 'Noch keine Notizen zu dieser Anfrage.',
        'needs_client' => 'Laufkundschaft hat keinen Datensatz, an dem eine Notiz hängen könnte.',
    ],

    'cancel' => [
        'title' => 'Diese Buchungsanfrage stornieren?',
        'body' => 'Die Anfrage wird als storniert markiert und in der Historie behalten — mit wer wann storniert hat.',
        'reason' => 'Grund',
        'choose_reason' => 'Grund wählen',
        'note' => 'Notiz',
        'note_placeholder' => 'Was die nächste Person wissen sollte — optional.',
        'keep' => 'Anfrage behalten',
        'confirm' => 'Buchungsanfrage stornieren',
        'cancelled' => 'Buchungsanfrage storniert.',
        'failed' => 'Das konnte nicht gespeichert werden. Bitte erneut versuchen.',
    ],

    'empty' => 'Keine Anfrage passt zu diesen Filtern.',
    'none_yet' => 'Noch keine Buchungsanfragen',
    'none_yet_hint' => 'Eine Anfrage wird festgehalten, wenn jemand eine Buchung beginnt und nicht abschließt — damit Sie Referenz und Leistungen haben, um zurückzurufen.',

    'showing' => ':from–:to von :total Anfragen werden angezeigt',
    'results' => [
        'zero' => 'Keine Anfragen',
        'one' => '1 Anfrage',
        'many' => ':count Anfragen',
    ],
];
