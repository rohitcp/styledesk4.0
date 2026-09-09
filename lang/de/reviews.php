<?php

declare(strict_types=1);

/*
| Kundenbewertungen und Rückmeldungen.
|
| Zwei Zielgruppen in einer Datei, getrennt durch ihre obersten Schlüssel: alles
| unter `page` und `email` liest jemand, der noch nie von StyleDesk gehört hat,
| alles andere der Betrieb. Die kundenseitige Sprache ist bewusst knapp — §33
| ist die ganze Vorgabe, und jeder zusätzliche Satz zwischen Link und Stern
| kostet eine Bewertung.
*/

return [

    'title' => 'Bewertungen und Rückmeldungen',

    'stars' => '{1} 1 Stern|[2,*] :count Sterne',

    /* Was die Sterne bedeuten — für die Bezeichnung neben dem gerade
       gewählten. */
    'rating_labels' => [
        1 => 'Sehr schlecht',
        2 => 'Schlecht',
        3 => 'Durchschnittlich',
        4 => 'Gut',
        5 => 'Ausgezeichnet',
    ],

    /* Wie weit der Betrieb mit einer Rückmeldung ist. */
    'statuses' => [
        'new' => 'Neu',
        'reviewing' => 'In Prüfung',
        'contacted' => 'Kundschaft kontaktiert',
        'resolved' => 'Erledigt',
        'closed' => 'Geschlossen',
        'no_action' => 'Kein Handlungsbedarf',
    ],

    /* Wie weit das Fragen gekommen ist, im Panel der Buchung selbst. */
    'request_statuses' => [
        'not_requested' => 'Nicht angefragt',
        'scheduled' => 'Eingeplant',
        'sent' => 'Gesendet',
        'completed' => 'Abgeschlossen',
    ],

    /* ---------------------------------------------------------- die Seite -- */

    'page' => [
        'title' => 'Wie war Ihr Besuch?',
        'question' => 'Wie war Ihr Erlebnis?',
        'question_hint' => 'Tippen Sie auf einen Stern. Mehr brauchen wir nicht.',

        'comment_label' => 'Erzählen Sie uns von Ihrem Besuch',
        'comment_optional' => 'Optional',
        'comment_placeholder' => 'Sagen Sie uns, was Ihnen gefallen hat oder was wir besser machen können.',

        'recommend_label' => 'Würden Sie uns weiterempfehlen?',
        'recommend' => [
            'yes' => 'Ja',
            'maybe' => 'Vielleicht',
            'no' => 'Nein',
        ],

        /* Erscheint, sobald eine niedrige Bewertung gewählt wird. Andere Worte,
           weil es eine andere Frage ist — §14. */
        'sorry_title' => 'Es tut uns leid, dass Ihr Besuch nicht Ihren Erwartungen entsprach.',
        'sorry_hint' => 'Bitte sagen Sie uns, wie wir es besser machen können.',
        'contact_label' => 'Ich möchte, dass sich jemand aus dem Betrieb bei mir meldet.',

        'submit' => 'Absenden',
        'submit_negative' => 'Rückmeldung senden',
        'choose_rating' => 'Wählen Sie zuerst eine Bewertung.',

        'thanks_title' => 'Danke für Ihre Rückmeldung!',
        'thanks_positive' => 'Schön, dass Ihnen Ihr Besuch gefallen hat. Möchten Sie Ihre Erfahrung teilen?',
        'thanks_negative' => 'Danke, dass Sie es uns sagen. Wir nehmen das zum Anlass, es richtigzustellen.',
        'thanks_contact' => 'Jemand aus dem Betrieb wird sich melden.',
        'google_cta' => 'Bewerten Sie uns bei Google',
        'done' => 'Fertig',

        'already' => 'Danke. Ihre Rückmeldung wurde bereits abgeschickt.',
    ],

    /* --------------------------------------------------------- die E-Mail -- */

    'email' => [
        'subject' => 'Wie war Ihr Besuch bei :business?',
        'preview' => 'Ein Tippen genügt.',
        'headline' => 'Wie war Ihr Besuch?',
        'intro' => 'Hallo :name — wir würden gern hören, wie es bei :business war.',
        'rate' => 'Bewerten Sie Ihren Besuch',
        'cta' => 'Rückmeldung geben',
    ],

    /* ------------------------------------------------------ die Einstellungen -- */

    'settings' => [
        'title' => 'Bewertungen und Rückmeldungen',
        'intro' => 'Fragen Sie nach einem abgeschlossenen Termin, wie der Besuch war, fangen Sie unzufriedene Kundschaft ab, bevor sie öffentlich wird, und leiten Sie die zufriedene auf Ihren Eintrag.',

        'enable' => 'Kundenbewertungen aktivieren',
        'enable_hint' => 'Ist das aus, wird nichts gesendet. Bereits abgegebene Bewertungen bleiben am Kundendatensatz und in Ihren Auswertungen.',
        'disabled_note' => 'Schalten Sie es ein, um zu wählen, wann und wie gefragt wird — und wohin zufriedene Kundschaft danach geleitet wird.',

        'timing' => 'Wann gefragt wird',
        'timing_hint' => 'Gemessen ab dem Moment, in dem der Termin abgeschlossen wird. Eine Stunde passt meist: lange genug, dass die Person gegangen ist, und früh genug, dass sie sich erinnert.',
        'delays' => [
            'immediate' => 'Sofort',
            '1h' => '1 Stunde nach Abschluss',
            '3h' => '3 Stunden nach Abschluss',
            '6h' => '6 Stunden nach Abschluss',
            'next_day' => 'Am nächsten Tag',
        ],

        'channel' => 'Wie gefragt wird',
        'channel_hint' => 'Wer keine Adresse hinterlegt hat, wird nicht gefragt. SMS stehen noch nicht zur Verfügung.',
        'channels' => [
            'email' => 'E-Mail',
            'sms' => 'SMS',
            'both' => 'SMS + E-Mail',
        ],
        'coming_soon' => 'Demnächst',

        'google' => 'Google-Bewertungen',
        'google_enable' => 'Zufriedener Kundschaft eine Google-Bewertung anbieten',
        'google_hint' => 'Wird nur denen gezeigt, die 4 oder 5 Sterne geben. Wer weniger gibt, wird stattdessen gefragt, was besser werden könnte, und nie auf einen öffentlichen Eintrag geschickt.',
        'google_urls' => 'Bewertungslinks je Standort',
        'google_urls_hint' => 'Jede Filiale hat ihren eigenen Google-Eintrag. Eine Filiale ohne Link bietet die Schaltfläche einfach nicht an.',
        'google_url_placeholder' => 'https://g.page/r/…',
        'no_locations' => 'Legen Sie einen Standort an, bevor Sie Google-Bewertungslinks setzen.',

        'saved' => 'Bewertungseinstellungen gespeichert.',
    ],

];
