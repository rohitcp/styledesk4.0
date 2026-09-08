<?php

declare(strict_types=1);

/*
| Die Worte, die die ganze Anwendung verwendet. Alles, was auf mehr als einer
| Ansicht erscheint, gehört hierher statt in die Datei dieser einen Ansicht —
| damit „Speichern“ einmal übersetzt wird und nicht in zwei Fassungen herauskommt.
*/

return [
    'retry' => 'Erneut versuchen',
    'save' => 'Speichern',
    'save_changes' => 'Änderungen speichern',
    'done' => 'Fertig',
    'cancel' => 'Abbrechen',
    'edit' => 'Bearbeiten',
    'delete' => 'Löschen',
    'remove' => 'Entfernen',
    'add' => 'Hinzufügen',
    'custom_color' => 'Eigene Farbe',
    'back' => 'Zurück',
    'close' => 'Schließen',
    'dismiss' => 'Schließen',
    'search' => 'Suchen',
    'filter' => 'Filtern',
    'clear' => 'Zurücksetzen',
    'clear_all' => 'Alles zurücksetzen',
    'apply' => 'Anwenden',
    'show_more' => 'Mehr anzeigen',
    'show_less' => 'Weniger anzeigen',
    'view' => 'Ansehen',
    'yes' => 'Ja',
    'no' => 'Nein',
    'optional' => '(optional)',
    'not_set' => 'Keine Angabe',
    'on' => 'An',
    'off' => 'Aus',
    'none' => 'Keine',
    'active' => 'Aktiv',
    'inactive' => 'Inaktiv',
    'saving' => 'Wird gespeichert…',
    'loading' => 'Wird geladen…',

    /**
     * Die gemeinsame Datumsauswahl (x-date-field).
     *
     * Monats- und Wochentagsnamen stehen hier nicht — Carbon kennt sie bereits
     * in jeder angebotenen Sprache, und eine von Hand gepflegte Liste von zwölf
     * wäre nur ein zweiter Ort, an dem sie sich widersprechen könnten.
     */
    'choose_a_date' => 'Datum wählen',
    'today' => 'Heute',
    'month' => 'Monat',
    'year' => 'Jahr',
    'previous_month' => 'Voriger Monat',
    'next_month' => 'Nächster Monat',
    'language' => 'Sprache',
    'coming_soon' => 'Demnächst',
    'setup_required' => 'Einrichtung erforderlich',
    'view_only' => 'Nur Ansicht',

    /*
     * Der gemeinsame Bild-Upload. Heute vom Profilbild der Mitarbeitenden und
     * künftig von jedem Bildfeld genutzt, deshalb stehen seine Worte hier und
     * nicht in einem Modul.
     */
    'upload' => [
        'choose' => 'Bild wählen',
        'progress' => 'Upload-Fortschritt',
        'cancel' => 'Upload abbrechen',
        'too_large' => 'Dieses Bild ist größer als 2 MB.',
        'failed' => 'Dieses Bild konnte nicht hochgeladen werden. Bitte erneut versuchen.',
    ],
    /** Ob jemand der Kontaktaufnahme zugestimmt hat. Gerendert von <x-consent-status>. */
    'consent' => [
        'opted_in' => 'Zugestimmt',
        'opted_out' => 'Abgelehnt',
    ],
    /** Wird gefragt, bevor etwas von einem Datensatz genommen wird. */
    'confirm' => [
        'deactivate' => 'Deaktivieren',
        'activate' => 'Aktivieren',
        'remove_tag_title' => 'Kunden-Tag entfernen?',
        'remove_tag' => '„:label“ von diesem Datensatz entfernen?',
        'remove_behavioral_title' => 'Verhaltens-Tag entfernen?',
        'remove_behavioral' => '„:label“ von diesem Datensatz entfernen?',
    ],

    /*
    | Die Validierungsmeldungen, die der Browser schreibt, während ein Formular
    | ausgefüllt wird. So formuliert, wie der Server denselben Wert ablehnt,
    | damit das Korrigieren vor dem Absenden und danach gleich klingt. :field
    | ist die Bezeichnung des Feldes selbst.
    */
    'validation' => [
        'required' => ':field ist erforderlich.',
        'email' => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'url' => 'Geben Sie eine gültige Webadresse ein, beginnend mit https://',
        'phone' => 'Geben Sie eine gültige Telefonnummer ein.',
        'date' => 'Geben Sie ein gültiges Datum ein.',
        'numeric' => ':field muss eine Zahl sein.',
        'integer' => ':field muss eine ganze Zahl sein.',
        'min' => ':field muss mindestens :min Zeichen lang sein.',
        'max' => ':field darf höchstens :max Zeichen lang sein.',
        'min_value' => ':field muss mindestens :min sein.',
        'max_value' => ':field darf höchstens :max sein.',
        'taken' => 'Dieser Wert wird bereits verwendet.',
    ],
    'type_a_time' => 'Uhrzeit eingeben, z. B. 14:30',

    /*
    | Der Rahmen, den jedes Layout zeichnet: das Hinweisband über der App-Leiste,
    | die Fußzeile darunter und das Banner, das erscheint, wenn die Dateien einer
    | Seite veraltet sind.
    |
    | Hier statt im Layout, weil vier Layouts dieselbe Fußzeile zeichnen — und
    | ein in jedes einzeln getippter Text ist ein Text, der in dreien davon
    | übersetzt wird.
    */
    'stale_assets' => 'Diese Seite ist nicht mehr aktuell, Teile davon funktionieren nicht. ',
    'reload' => 'Neu laden',
    'legal' => 'Rechtliches',
    'terms' => 'AGB',
    'privacy' => 'Datenschutz',
    'support' => 'Support',
    'all_rights_reserved' => '© :year StyleDesk. Alle Rechte vorbehalten.',

    'banner' => [
        'watch_now' => 'Jetzt ansehen: Erste Schritte mit StyleDesk',
        /* trans_choice, nicht Str::plural(): Der Helfer kennt nur Englisch und
           hätte in jeder anderen Sprache „2 day“ geschrieben. Jede Sprache
           nennt hier ihren eigenen Plural, und der ganze Satz ist eine einzige
           Zeichenkette, damit auch die Wortstellung abweichen darf. */
        'trial_remaining' => '{0} Ihre kostenlose Testphase endet heute|{1} Noch :count Tag in Ihrer kostenlosen Testphase|[2,*] Noch :count Tage in Ihrer kostenlosen Testphase',
        'trial_ending' => 'Ihre Testphase endet bald',
        'subscribe' => 'Jetzt abonnieren',
    ],

    'session' => [
        'expiring' => 'Ihre Sitzung läuft bald ab',
        'expiring_body' => 'Zu Ihrer Sicherheit werden Sie abgemeldet, weil es keine Aktivität gab.',
        /* :time ist der laufende Countdown, von der Ansicht in ein eigenes Element gesetzt. */
        'countdown' => 'Sitzung läuft ab in :time',
        'sign_out' => 'Abmelden',
        'stay' => 'Angemeldet bleiben',
        'expired' => 'Ihre Sitzung ist abgelaufen',
        'expired_body' => 'Ihre Sitzung endete, weil es keine Aktivität gab. Melden Sie sich erneut an, um fortzufahren.',
        'sign_in' => 'Anmelden',
    ],
];
