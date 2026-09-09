<?php

declare(strict_types=1);

return [

    'title' => 'E-Mail',
    'intro' => 'Senden Sie Ihrer Kundschaft E-Mails direkt aus StyleDesk.',

    'settings' => [
        'enable' => 'Kunden-E-Mail aktivieren',
        'enable_hint' => 'Ist das aus, verschwindet „E-Mail senden“ aus den Kundenprofilen. Der Verlauf bleibt, wo er ist.',
        'enabled' => 'Aktiviert',
        'disabled' => 'Deaktiviert',

        'default_method' => 'Standard-Versandweg',
        'default_hint' => 'Ein Dienstleister verschickt Ihre Kunden-E-Mails. Sie können jederzeit wechseln.',
        'active' => 'AKTIV',
        'coming_soon' => 'Demnächst',
        'use_this' => 'Diesen verwenden',

        'sender' => 'Absender',
        'sender_hint' => 'Wie Ihre E-Mails unterzeichnet sind, unabhängig vom Versandweg.',
        'sender_name' => 'Absendername',
        'sender_name_hint' => 'Der Name, den Ihre Kundschaft sieht. Standard ist Ihr Betriebsname.',
        'reply_to' => 'Antwortadresse',
        'reply_to_hint' => 'Wohin die Antwort geht. Ohne sie erreicht eine Antwort niemanden.',
        'reply_to_gmail' => 'Wird nicht verwendet, solange Gmail versendet — Antworten gehen direkt in Ihr verbundenes Postfach.',
        'preview' => 'Ihre Kundschaft sieht',

        'send_test' => 'Test-E-Mail senden',
        'test_hint' => 'Geht an Ihre eigene Adresse, damit Sie genau sehen, was ankommt.',
        'test_sent' => 'Test-E-Mail an :email gesendet.',
        'test_failed' => 'Der Test konnte nicht gesendet werden. :reason',
        'test_subject' => 'Test-E-Mail von StyleDesk',
        'test_body' => "Dies ist eine Test-E-Mail von :name.\n\nWenn Sie das lesen können, ist Ihre Kunden-E-Mail eingerichtet und funktioniert. An Ihre Kundschaft wurde nichts gesendet.",

        'saved' => 'Ihre E-Mail-Einstellungen wurden gespeichert.',
        'save' => 'Speichern',
    ],

    'providers' => [
        'styledesk' => [
            'name' => 'StyleDesk-E-Mail',
            'description' => 'Senden Sie direkt über StyleDesk, ohne ein externes E-Mail-Konto zu verbinden.',
        ],
        'gmail' => [
            'name' => 'Gmail verbinden',
            'description' => 'Verbinden Sie Ihr geschäftliches Gmail- oder Google-Workspace-Konto und senden Sie von Ihrer vorhandenen Adresse.',
        ],
    ],

    'connection' => [
        'connected' => 'Verbunden',
        'disconnected' => 'Getrennt',
        'needs_attention' => 'Prüfung nötig',
    ],

    'send' => [
        'action' => 'E-Mail senden',
        'title' => 'E-Mail senden',
        'to' => 'An',
        'from' => 'Von',
        /* Was die Kundschaft tatsächlich im Postfach sieht. Auch auf der
           Einstellungsseite klar gesagt, denn wer die eigene Adresse erwartet
           hat, soll es hier erfahren und nicht von einer Kundin. */
        'from_via' => ':name über StyleDesk',
        'template' => 'E-Mail-Vorlage',
        'no_template' => 'Keine Vorlage',
        'related_booking' => 'Zugehörige Buchung',
        'no_booking' => 'Keine',
        'subject' => 'Betreff',
        'message' => 'Nachricht',
        'cancel' => 'Abbrechen',
        'submit' => 'E-Mail senden',
        'sending' => 'Wird gesendet…',
        'sent' => 'E-Mail an :name gesendet',
    ],

    'statuses' => [
        'queued' => 'In Warteschlange',
        'sent' => 'Gesendet',
        'delivered' => 'Zugestellt',
        'failed' => 'Fehlgeschlagen',
    ],

    'history' => [
        'title' => 'E-Mail-Verlauf',
        'empty' => 'An diese Person wurde noch keine E-Mail gesendet.',
        'sent_by' => 'Gesendet von :name',
        'system' => 'StyleDesk-System',
        'view' => 'Ansehen',
    ],

    'errors' => [
        'disabled' => 'Die Kunden-E-Mail ist für diesen Betrieb abgeschaltet. Schalten Sie sie unter Einstellungen → E-Mail ein.',
        'no_provider' => 'Es ist kein Versandweg eingerichtet. Wählen Sie einen unter Einstellungen → E-Mail.',
        'no_address' => 'Für diese Person ist keine E-Mail-Adresse hinterlegt.',
        'failed' => 'Die E-Mail konnte nicht gesendet werden. Sie steht als fehlgeschlagen am Datensatz, und Sie können es erneut versuchen.',
        'gmail_not_connected' => 'Es ist kein Gmail-Konto verbunden. Verbinden Sie eines unter Einstellungen → E-Mail.',
        'reconnect_gmail' => 'Gmail neu verbinden',
    ],

    /*
    | Die Vorlagen, von denen eine Nachricht ausgehen kann.
    |
    | Gerüst, keine Umschläge: Die Leiste legt eine hinein, die sendende Person
    | bearbeitet sie, und gespeichert wird, was tatsächlich gesendet wurde.
    */
    'templates' => [
        'appointment_follow_up' => [
            'name' => 'Nachfassen nach dem Termin',
            'subject' => 'Danke für Ihren Besuch bei {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\ndanke, dass Sie am {{booking_date}} da waren. Wir hoffen, Sie sind mit Ihrer {{service_name}} zufrieden.\n\nFalls Sie irgendetwas angepasst haben möchten, antworten Sie einfach auf diese E-Mail, wir kümmern uns darum.\n\nVielen Dank,\n{{business_name}}",
        ],
        'appointment_information' => [
            'name' => 'Termininformation',
            'subject' => 'Ihr Termin bei {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\nkurz zu Ihrem Termin am {{booking_date}} um {{booking_time}} bei {{staff_name}}.\n\nBuchungsnummer: {{booking_reference}}\n\nWenn Sie etwas ändern müssen, antworten Sie auf diese E-Mail oder rufen Sie uns an.\n\nVielen Dank,\n{{business_name}}",
        ],
        'payment_reminder' => [
            'name' => 'Zahlungserinnerung',
            'subject' => 'Eine Erinnerung an Ihren offenen Betrag bei {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\neine freundliche Erinnerung: Ihr offener Betrag beläuft sich auf {{balance_due}}.\n\nSie können ihn beim nächsten Besuch begleichen, oder antworten Sie auf diese E-Mail, dann schicken wir Ihnen einen Zahlungslink.\n\nVielen Dank,\n{{business_name}}",
        ],
        'outstanding_balance' => [
            'name' => 'Offener Betrag',
            'subject' => 'Offener Betrag für Ihren Besuch am {{booking_date}}',
            'body' => "Hallo {{client_first_name}},\n\nfür Ihren Besuch am {{booking_date}} steht noch ein Betrag von {{balance_due}} offen.\n\nBuchungsnummer: {{booking_reference}}\n\nFalls das ein Irrtum sein sollte, antworten Sie bitte, wir prüfen es sofort.\n\nVielen Dank,\n{{business_name}}",
        ],
        'thank_you' => [
            'name' => 'Dankeschön',
            'subject' => 'Danke von {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\ndanke, dass Sie sich für {{business_name}} entschieden haben. Es war schön, Sie bei uns zu haben.\n\nWir freuen uns auf Ihren nächsten Besuch.\n\nVielen Dank,\n{{business_name}}",
        ],
        'service_follow_up' => [
            'name' => 'Nachfassen zur Leistung',
            'subject' => 'Wie ist Ihre {{service_name}}?',
            'body' => "Hallo {{client_first_name}},\n\nIhre {{service_name}} bei {{staff_name}} ist schon eine Weile her. Wir wollten hören, wie Sie damit zurechtkommen.\n\nWenn Sie eine Auffrischung möchten oder Fragen haben, antworten Sie einfach auf diese E-Mail.\n\nVielen Dank,\n{{business_name}}",
        ],
        'membership_information' => [
            'name' => 'Informationen zur Mitgliedschaft',
            'subject' => 'Mitgliedschaft bei {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\nwir dachten, unsere Mitgliedschaften könnten Sie interessieren: Stammkundschaft bekommt bessere Preise und bevorzugte Termine.\n\nAntworten Sie auf diese E-Mail, dann schicken wir Ihnen die Einzelheiten.\n\nVielen Dank,\n{{business_name}}",
        ],
        'general_message' => [
            'name' => 'Allgemeine Nachricht',
            'subject' => 'Eine Nachricht von {{business_name}}',
            'body' => "Hallo {{client_first_name}},\n\n\nVielen Dank,\n{{business_name}}",
        ],
    ],

    'gmail' => [
        'connect' => 'Gmail verbinden',
        'reconnect' => 'Neu verbinden',
        'disconnect' => 'Trennen',
        'connected' => 'Gmail verbunden. Ihre Kunden-E-Mails gehen jetzt von :email aus.',
        'connected_on' => 'Verbunden am :date',
        'disconnected' => 'Gmail wurde getrennt. Kunden-E-Mails laufen wieder über die StyleDesk-E-Mail.',
        'failed' => 'Gmail konnte nicht verbunden werden. :reason',
        'state_mismatch' => 'Dieser Verbindungsversuch konnte nicht überprüft werden. Bitte erneut versuchen.',
        'no_code' => 'Google hat nichts zurückgeschickt, womit sich verbinden ließe.',
        'no_refresh_token' => 'Google hat für dieses Konto keine dauerhafte Berechtigung erteilt.',
        'reconnect_needed' => 'Die Gmail-Verbindung funktioniert nicht mehr und muss neu hergestellt werden.',
        'unknown_error' => 'Google hat nicht gesagt, warum.',
        'replies_note' => 'Antworten gehen direkt in dieses Postfach. Sie werden nicht nach StyleDesk zurückgeholt.',
    ],

    'variables' => [
        'title' => 'Sie können verwenden',
        'hint' => 'Diese werden beim Laden der Vorlage ersetzt.',
    ],
];
