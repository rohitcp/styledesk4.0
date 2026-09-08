<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Betriebsaktivität
|--------------------------------------------------------------------------
|
| Das Panel hinter dem Aktivitätssymbol in der App-Leiste: wer wann was getan
| hat, und ein Weg zu dem Datensatz, dem es widerfahren ist.
|
| Die Ein-Wort-Typen sind hier der springende Punkt. „Buchung“, „Absage“,
| „Zahlung“ — wer diese Spalte überfliegt, soll die gesuchte Art von Vorgang
| finden, ohne einen Satz zu lesen. Der Satz darunter kann dann von etwas
| anderem handeln.
|
*/

return [

    'title' => 'Betriebsaktivität',
    'intro' => 'Was im Betrieb geschehen ist.',
    'open' => 'Aktivität',
    'close' => 'Aktivität schließen',
    'mark_read' => 'Alle als gelesen markieren',
    'unread' => ':count neu',
    'unread_capped' => '99+ neu',
    'loading' => 'Wird geladen…',
    'more' => 'Mehr anzeigen',
    'empty' => 'Bisher ist nichts geschehen.',
    'empty_hint' => 'Buchungen, Zahlungen und Änderungen im Betrieb erscheinen hier, sobald sie passieren.',
    'empty_filtered' => 'Von dieser Art bisher nichts.',

    'today' => 'Heute',
    'yesterday' => 'Gestern',
    'earlier' => 'Früher',

    'by' => 'Von :name',
    'system' => 'StyleDesk',

    /*
    | Je ein Wort, nie zwei. Der Typ ist eine Bezeichnung, die man überfliegt,
    | kein Satz, den man liest.
    */
    'kinds' => [
        'booking' => 'Buchung',
        'reschedule' => 'Verschiebung',
        'cancel' => 'Absage',
        'checkin' => 'Check-in',
        'checkout' => 'Check-out',
        'noshow' => 'Nichterscheinen',
        'client' => 'Kundschaft',
        'note' => 'Notiz',
        'file' => 'Datei',
        'payment' => 'Zahlung',
        'deposit' => 'Anzahlung',
        'refund' => 'Erstattung',
        'staff' => 'Personal',
        'schedule' => 'Dienstplan',
        'service' => 'Leistung',
        'resource' => 'Ressource',
        'email' => 'E-Mail',
        'sms' => 'SMS',
        'review' => 'Bewertung',
        'coupon' => 'Gutschein',
        'giftcard' => 'Geschenkgutschein',
        'login' => 'Anmeldung',
        'settings' => 'Einstellungen',
    ],

    /* Die Filter oben. */
    'groups' => [
        'all' => 'Alles',
        'bookings' => 'Buchungen',
        'clients' => 'Kundschaft',
        'payments' => 'Zahlungen',
        'staff' => 'Personal',
        'scheduling' => 'Planung',
        'communication' => 'Kommunikation',
        'system' => 'System',
    ],

    /* Wohin die Zeile führt, wenn man sie anklickt. */
    'links' => [
        'booking' => 'Buchung ansehen',
        'client' => 'Kundendatensatz ansehen',
        'staff' => 'Personaldatensatz ansehen',
        'schedule' => 'Dienstplan ansehen',
        'service' => 'Leistung ansehen',
        'resource' => 'Ressource ansehen',
        'payment' => 'Zahlung ansehen',
    ],

    /*
    | Sätze, die diese Ansicht selbst formuliert — für Quellen, die Tatsachen
    | speichern statt Prosa. Alles aus der Kundenchronik kommt bereits
    | geschrieben an und wird so gezeigt, wie es gespeichert wurde.
    */
    'sentences' => [
        'status' => 'Buchung :reference wurde als :status markiert.',
        'reason' => 'Grund: :reason.',
        'schedule' => 'Dienstplan für :name veröffentlicht — :count Schichten.',
    ],

    /*
    | Verwaltungsänderungen, nach der im Prüfprotokoll erfassten Aktion
    | verschlüsselt. Was keinen Eintrag hat, fällt auf die Worte der Aktion
    | selbst zurück statt auf gar nichts.
    */
    'audit' => [
        'fallback' => ':action — :name',
        'staff.created' => ':name gehört jetzt zum Team.',
        'staff.edited' => 'Die Angaben von :name wurden aktualisiert.',
        'staff.role_changed' => 'Die Rolle von :name wurde geändert.',
        'staff.deleted' => ':name wurde aus dem Team entfernt.',
        'staff.invitation_sent' => ':name wurde eingeladen.',
        'auth.login' => ':name hat sich angemeldet.',
        'auth.logout' => ':name hat sich abgemeldet.',
        'auth.password_reset' => 'Das Passwort von :name wurde zurückgesetzt.',
    ],

];
