<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
|
| E-Mail-Kampagnen: was der Betrieb an seine gesamte Kundenliste schreibt — im
| Unterschied zum E-Mail-Modul, das ein Gespräch mit einer einzelnen Person ist.
|
*/

return [

    'title' => 'E-Mail-Marketing',
    'subtitle' => 'Kampagnen an Ihre Kundenliste',
    'intro' => 'Schreiben Sie Ihrer Kundschaft als Gruppe — Angebote, Neuigkeiten und Erinnerungen — und sehen Sie, was daraus wurde.',

    'create' => 'E-Mail-Kampagne anlegen',
    'edit' => 'Kampagne bearbeiten',
    'saved' => 'Kampagne gespeichert',
    'deleted' => 'Kampagne gelöscht',
    'delete' => 'Entwurf löschen',
    'delete_confirm' => 'Diesen Entwurf löschen? Er wurde an niemanden gesendet, und das lässt sich nicht rückgängig machen.',
    'save_draft' => 'Entwurf speichern',
    'cancel' => 'Abbrechen',
    'search' => 'Kampagnen durchsuchen…',
    'all_statuses' => 'Alle Status',
    'filters_active' => 'Filter',
    'clear' => 'Zurücksetzen',

    'summary' => [
        'campaigns' => 'Kampagnen',
        'sent' => 'Gesendete E-Mails',
        'delivery_rate' => 'Zustellrate',
        'open_rate' => 'Öffnungsrate',
        'click_rate' => 'Klickrate',
        'unsubscribed' => 'Abmeldungen',
    ],

    'statuses' => [
        'draft' => 'Entwurf',
        'scheduled' => 'Geplant',
        'sending' => 'Wird gesendet',
        'sent' => 'Gesendet',
        'paused' => 'Pausiert',
        'cancelled' => 'Abgebrochen',
        'failed' => 'Fehlgeschlagen',
    ],

    'table' => [
        'name' => 'Kampagne',
        'audience' => 'Zielgruppe',
        'recipients' => 'Empfänger',
        'scheduled' => 'Geplant',
        'sent' => 'Gesendet',
        'delivered' => 'Zugestellt',
        'opened' => 'Geöffnet',
        'clicked' => 'Geklickt',
        'author' => 'Angelegt von',
        'status' => 'Status',
    ],

    /* Schritt eins: was die E-Mail ist und von wem sie kommt. */
    'details' => [
        'title' => 'Angaben zur Kampagne',
        'intro' => 'Wie diese Kampagne heißt und was Ihre Kundschaft im Postfach sieht.',
        'name' => 'Name der Kampagne',
        'name_hint' => 'Für Ihre eigene Übersicht. Ihre Kundschaft sieht das nie.',
        'name_placeholder' => 'Massage-Aktion September',
        'subject' => 'Betreff der E-Mail',
        'subject_placeholder' => '20 % auf Ihre nächste Massage',
        'preview_text' => 'Vorschautext',
        'preview_hint' => 'Die Zeile, die in den meisten Postfächern nach dem Betreff erscheint.',
        'preview_placeholder' => 'Buchen Sie heute Ihren September-Termin.',
        'from_name' => 'Absendername',
        'reply_to' => 'Antwortadresse',
        'reply_hint' => 'Wohin Antworten gehen. Leer lassen, um die E-Mail des Betriebs zu verwenden.',
    ],

    /* Schritt zwei: an wen sie geht. */
    'audience' => [
        'title' => 'Zielgruppe',
        'intro' => 'An wen diese Kampagne geht. Die Regeln greifen beim Versand — eine Liste, die bis dahin wächst, wächst also mit.',
        'scope' => 'Kundschaft',
        'locations' => 'Standorte',
        'tags' => 'Tags',
        'staff' => 'Betreut von',
        'services' => 'Hatte die Leistung',
        'lapsed' => 'War nicht mehr da seit',
        'visited' => 'War da innerhalb von',
        'booking' => 'Anstehender Termin',
        'days' => ':days Tagen',
        'any' => 'Beliebig',
        'has_upcoming' => 'Hat einen gebucht',
        'no_upcoming' => 'Hat nichts gebucht',

        'scopes' => [
            'all' => 'Gesamte Kundschaft',
            'active' => 'Aktive Kundschaft',
        ],

        /*
        | Die Zielgruppenspalte in der Liste, in Worten.
        |
        | Eine eigene Gruppe, weil vier davon sonst mit den Feldbezeichnungen
        | oben kollidieren würden — „War nicht mehr da seit“ ist eine
        | Feldbezeichnung, „kein Besuch seit 90 Tagen“ ein Satz über eine
        | gespeicherte Kampagne; der zweite verdrängte den ersten stillschweigend,
        | als sie sich einen Schlüssel teilten.
        */
        'said' => [
            'locations' => '{1} 1 Standort|[2,*] :count Standorte',
            'tags' => '{1} 1 Tag|[2,*] :count Tags',
            'staff' => '{1} 1 Fachkraft|[2,*] :count Fachkräfte',
            'services' => '{1} 1 Leistung|[2,*] :count Leistungen',
            'lapsed' => 'kein Besuch seit :days Tagen',
            'visited' => 'Besuch in den letzten :days Tagen',
            'has_upcoming' => 'hat einen anstehenden Termin',
            'no_upcoming' => 'nichts gebucht',
        ],
    ],

    /*
    | Die Schätzung. Drei Zahlen statt einer, weil „1.248 Personen“ die zwei
    | Tatsachen verbirgt, die eine Inhaberin vor dem Senden braucht.
    */
    'estimate' => [
        'title' => 'Geschätzte Empfänger',
        'eligible' => 'Infrage kommend',
        'unsubscribed' => 'Abgemeldet',
        'invalid' => 'Ungültige E-Mail',
        'total' => 'Passt zu Ihren Regeln',
        'counting' => 'Wird gezählt…',
        'hint' => 'Jetzt gezählt. Beim Versand greifen die Regeln erneut, die Zahl kann sich also ändern.',
        'none' => 'Bisher passt niemand zu diesen Regeln.',
        'all_unsubscribed' => 'Alle, die zu diesen Regeln passen, haben Marketing-E-Mails abbestellt.',
    ],

    /* Was gebaut ist und was nicht. */
    'next' => [
        'title' => 'Kommt noch',
        'intro' => 'Diese Kampagne lässt sich beschreiben und speichern. Gestalten und Senden sind die nächsten Schritte.',
        'design' => 'E-Mail gestalten',
        'preview' => 'Vorschau und Test',
        'send' => 'Senden oder planen',
        'soon' => 'Demnächst',
    ],

    'empty' => 'Noch keine Kampagnen.',
    'empty_hint' => 'Legen Sie eine an, um Ihrer Kundschaft als Gruppe zu schreiben.',
    'no_matches' => 'Keine Kampagne passt zu dieser Suche.',

    'results' => [
        'zero' => 'Keine Kampagnen gefunden',
        'one' => '1 Kampagne gefunden',
        'many' => ':count Kampagnen gefunden',
    ],
    'showing' => ':from–:to von :total Kampagnen werden angezeigt',
    'actions_for' => 'Aktionen für :name',

];
