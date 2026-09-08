<?php

declare(strict_types=1);

/*
| Das Modul Markenauftritt: das Formular und seine Vorschauen.
|
| Die Vorschaufelder stellen die App, die Buchungsseite, eine E-Mail und einen
| Beleg dar — die Worte darin sind also Beispielinhalte, und sie werden aus
| demselben Grund übersetzt wie der Rest der Ansicht: Wer auf Spanisch
| nachsieht, was der eigene Markenauftritt erzeugt, sollte es lesen können.
*/

return [
    'title' => 'Markenauftritt',
    'intro' => 'Ihr Logo, Favicon und Ihre Farben — verwendet in StyleDesk, auf Ihrer Buchungsseite, in Bestätigungs- und Erinnerungs-E-Mails sowie auf Belegen und Rechnungen.',
    'saved' => 'Einstellungen zum Markenauftritt aktualisiert.',
    'save_failed' => 'Ihr Markenauftritt konnte nicht gespeichert werden. Bitte erneut versuchen.',
    'reset_done' => 'Markenauftritt auf den StyleDesk-Standard zurückgesetzt.',
    'correct_fields' => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',

    'reset' => 'Auf Standard-Markenauftritt zurücksetzen',
    'remove_confirm' => 'Dieses Bild entfernen? Es wird beim Speichern gelöscht.',
    'reset_confirm' => 'Den Markenauftritt auf den StyleDesk-Standard zurücksetzen? Logo, Favicon und Farben werden entfernt.',

    'logo' => [
        'title' => 'Logo des Betriebs',
        'hint' => 'PNG, JPG, SVG oder WEBP, bis 2 MB. Rund 400 × 120 px funktioniert gut. Ein transparenter Hintergrund bleibt erhalten.',
        'upload' => 'Logo hochladen',
        'replace' => 'Logo ersetzen',
    ],

    'favicon' => [
        'title' => 'Favicon / App-Symbol',
        'hint' => 'PNG, SVG oder ICO, bis 2 MB. Verwenden Sie ein quadratisches Bild — 512 × 512 px ist ideal.',
        'upload' => 'Favicon hochladen',
        'replace' => 'Favicon ersetzen',
    ],

    'upload' => [
        'uploading' => 'Wird hochgeladen…',
        'removed' => 'Entfernt. Zum Bestätigen speichern.',
        'failed' => 'Diese Datei konnte nicht hochgeladen werden.',
        'mimes' => 'Verwenden Sie eine :formats-Datei.',
        'too_large' => 'Die Datei darf höchstens 2 MB groß sein.',
    ],

    'colours' => [
        'title' => 'Markenfarben',
        'hint' => 'Wählen Sie eine Farbe oder tippen Sie einen Hex-Wert. Der Hover-Ton und die Textfarbe auf Ihren Schaltflächen werden daraus abgeleitet.',
        'primary' => 'Primär',
        'primary_hint' => 'Schaltflächen, Links und App-Leiste.',
        'secondary' => 'Sekundär',
        'secondary_hint' => 'Unterstützende Akzente.',
        'accent' => 'Akzent',
        'accent_hint' => 'Abzeichen und kleine Hervorhebungen.',
        'picker_label' => 'Farbauswahl :name',
        'invalid' => 'Geben Sie eine Hex-Farbe ein, etwa #3d348b.',
        'contrast' => 'Weißer Text auf Ihrer Primärfarbe:',
        'contrast_warning' => 'App-Leiste, Kopf Ihrer Buchungsseite und E-Mail-Kopf setzen weißen Text auf diese Farbe, und in diesem Ton ist er schwer zu lesen. Eine dunklere Farbe löst das meist.',
        'required_primary' => 'Wählen Sie eine Primärfarbe.',
        'required_secondary' => 'Wählen Sie eine Sekundärfarbe.',
        'required_accent' => 'Wählen Sie eine Akzentfarbe.',
    ],

    /*
     * Die WCAG-Stufen. In ihrer Standardform belassen: „AA“ ist der Name einer
     * Konformitätsstufe, kein englisches Wort — wer sie in einer Spezifikation
     * sucht, sucht genau diese zwei Buchstaben.
     */
    'grades' => [
        'aaa' => 'AAA',
        'aa' => 'AA',
        'large' => 'Nur großer Text',
        'fails' => 'Nicht bestanden',
    ],

    'preview' => [
        'title' => 'Vorschau',
        'hint' => 'Aktualisiert sich, während Sie oben die Farben ändern.',
        'trial' => 'Noch 0 Tage in Ihrer kostenlosen Testphase',
        'in_app' => 'In StyleDesk',
        'booking_page' => 'Ihre Buchungsseite',
        'emails' => 'Bestätigungs-, Erinnerungs- und Einladungs-E-Mails',
        'receipts' => 'Belege und Rechnungen',

        'your_logo' => 'Ihr Logo',
        'book_appointment' => 'Termin buchen',
        'confirmed' => 'Bestätigt',
        'new' => 'Neu',
        'link_sentence' => 'Ein Link sieht aus wie :link.',
        'this_one' => 'dieser hier',

        'book_online' => 'Buchen Sie online, jederzeit',
        'select' => 'Auswählen',
        'continue' => 'Weiter',
        'minutes' => ':count Min.',

        'email_subject' => 'Ihr Termin ist bestätigt',
        'email_body' => 'Donnerstag, 4. September, 14:00 Uhr bei Priya im :business.',
        'view_appointment' => 'Termin ansehen',
        'email_footer' => 'Gesendet von :business über StyleDesk',

        'receipt' => 'Beleg',
        'tax' => 'Steuer',
        'total' => 'Gesamt',

        'where_used' => 'Wo das verwendet wird',
        'where_used_body' => 'Die StyleDesk-App, Ihre Buchungsseiten, Terminbestätigungen, Erinnerungen, Team-Einladungen, Belege, Rechnungen, Geschenkgutscheine und Kundenbenachrichtigungen.',
        'representations' => 'Die Felder oben sind Darstellungen, nicht die Vorlagen selbst.',
    ],
];
