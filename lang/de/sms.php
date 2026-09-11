<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the desk reads about a text message: where it got to, and what it was
| about.
|
*/

return [
    'title' => 'SMS',

    'statuses' => [
        'queued' => ['label' => 'In Warteschlange'],
        'sending' => ['label' => 'Wird gesendet'],
        'sent' => ['label' => 'Gesendet'],
        'delivered' => ['label' => 'Zugestellt'],
        'failed' => ['label' => 'Fehlgeschlagen'],
        'rejected' => ['label' => 'Abgelehnt'],
        'expired' => ['label' => 'Abgelaufen'],
        'opted_out' => ['label' => 'Abgemeldet'],
    ],

    'types' => [
        'reply' => 'Kundenantwort',
        'test' => 'Testnachricht',
        'booking_confirmation' => 'Buchungsbestätigung',
        'appointment_reminder' => 'Terminerinnerung',
        'booking_rescheduled' => 'Buchung verschoben',
        'booking_cancelled' => 'Buchung storniert',
        'birthday' => 'Geburtstagsgrüße',
        'membership' => 'Mitgliedschafts-Hinweise',
    ],

    'registration' => [
        'not_started' => 'Nicht begonnen',
        'submitted' => 'Eingereicht',
        'pending' => 'Ausstehend',
        'approved' => 'Genehmigt',
        'rejected' => 'Abgelehnt',
        'suspended' => 'Gesperrt',
    ],

    'settings' => [
        'sending_from' => 'Senden von :number',
        'test' => 'Testnachricht senden',
        'test_hint' => 'Sendet eine SMS über genau denselben Weg wie eine Buchungsbestätigung und protokolliert sie wie jede andere.',
        'test_to' => 'Senden an',
        'send_test' => 'SMS senden',
        'test_body' => 'Testnachricht von :business. StyleDesk SMS funktioniert.',
        'test_sent' => 'Testnachricht an :number über :provider gesendet.',
        'test_failed' => 'Die Testnachricht konnte nicht gesendet werden. :reason',
        'test_unknown' => 'Der Anbieter nannte keinen Grund.',
        'test_bad_number' => 'Das sieht nicht nach einer Telefonnummer aus.',
        'test_live' => 'Telnyx ist verbunden — die Nachricht erreicht ein echtes Telefon und wird berechnet.',
        'test_local' => 'Kein Netzbetreiber verbunden — es erreicht kein Telefon, die Nachricht wird nur protokolliert.',
        'title' => 'SMS-Einstellungen',
        'intro' => 'Was StyleDesk deinen Kunden per SMS schickt, von welcher Nummer, und was du dafür ausgeben willst.',
        'sender' => 'SMS-Nummer',
        'sender_hint' => 'StyleDesk kümmert sich um die Nummer und ihre Registrierung beim Netzbetreiber. An US-Mobilnummern kann erst gesendet werden, wenn die Registrierung genehmigt ist.',
        'no_number' => 'Noch nicht zugewiesen',
        'registration' => 'Registrierung',
        'saved' => 'SMS-Einstellungen gespeichert.',
        'enable' => 'StyleDesk SMS aktivieren',
        'enable_hint' => 'SMS werden nur gesendet, solange dies aktiv ist.',
        'disabled_note' => 'SMS sind aus. Es wird nichts gesendet und nichts weiter gefragt.',
        'messages' => 'Transaktionsnachrichten',
        'messages_hint' => 'Welche SMS rausgehen. Jede geht nur an Kunden, die zugestimmt haben.',
        'not_yet' => 'Noch nicht verfügbar.',
        'reminders' => 'Terminerinnerungen',
        'reminders_hint' => 'Wie lange vor einem Termin eine Erinnerung rausgeht. Mehrere auswählen, um mehrere zu senden.',
        'hours_before' => '{1} 1 Stunde vorher|[2,*] :count Stunden vorher',
        'birthday_at' => 'Geburtstagsgrüße senden um',
        'birthday_at_hint' => 'In der Ortszeit des Standorts.',
        'spend' => 'Nutzung und Limits',
        'spend_hint' => 'Eine Obergrenze, damit ein Import oder ein Fehler nicht die ganze Kundenliste antextet.',
        'monthly_limit' => 'Monatliches Nachrichtenlimit',
        'no_limit' => 'Kein Limit',
        'alert_at' => 'Warnen bei',
        'used_this_month' => 'Nachrichten diesen Monat',
        'segments_this_month' => 'Segmente diesen Monat',
    ],

    'errors' => [
        'disabled' => 'StyleDesk SMS ist auf dieser Installation deaktiviert.',
        'no_sender' => 'Keine Absendernummer gesetzt. Trage eine unter Einstellungen → SMS ein oder setze TELNYX_FROM_NUMBER.',
    ],

    'confirmation' => [
        'not_requested' => 'Nicht angefragt',
        'pending' => 'Wartet auf Kunden',
        'confirmed' => 'Vom Kunden bestätigt',
        'cancellation_requested' => 'Stornierung angefragt',
        'needs_review' => 'Prüfen',
    ],
];
