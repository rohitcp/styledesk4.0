<?php

declare(strict_types=1);

/*
| Das Modul Betriebseinstellungen: die Nur-Lese-Ansicht und ihr
| Bearbeitungsformular.
|
| Die Feldbezeichnungen teilen sich beide Ansichten bewusst. Wenn „Haupt-E-Mail“
| auf der Ansichtsseite das eine und im Formular das andere benennt, ist das
| genau das Auseinanderdriften, dem ein gemeinsamer Schlüssel vorbeugt.
*/

return [
    'title' => 'Betrieb',
    'intro' => 'Name, Art, Kontaktdaten und Betriebskonfiguration.',
    'edit' => 'Betrieb bearbeiten',
    'edit_title' => 'Betrieb bearbeiten',
    'edit_intro' => 'Aktualisieren Sie Ihre Betriebsangaben. Standorte, Öffnungszeiten, Währungen und Buchungsregeln haben eigene Einstellungsseiten.',
    'saved' => 'Betriebseinstellungen aktualisiert.',
    'save_failed' => 'Ihre Änderungen konnten gerade nicht gespeichert werden. Bitte erneut versuchen.',
    'correct_fields' => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',

    'cards' => [
        'information' => 'Angaben zum Betrieb',
        'contact' => 'Kontaktdaten',
        'address' => 'Anschrift des Betriebs',
        'address_hint' => 'Ihre Hauptanschrift. Insgesamt :count Standorte.',
        'regional' => 'Regionale Einstellungen',
        'branding' => 'Logo & Markenauftritt',
        'branding_hint' => 'Wird unter Markenauftritt eingerichtet; hier zur Einordnung gezeigt.',
        'languages' => 'Sprachen',
        'languages_hint' => 'Werden unter Sprachen eingerichtet; hier zur Einordnung gezeigt.',
        'currency' => 'Währung',
        'currency_hint' => 'Wird unter Währung eingerichtet; hier zur Einordnung gezeigt.',
        'online_booking' => 'Onlinebuchung',
        'online_booking_hint' => 'Bei der Anmeldung festgelegt. Das Modul Onlinebuchung kommt bald.',
        'security' => 'Sicherheit',
        'defaults' => 'Voreinstellungen',
        'defaults_hint' => 'Ausgangspunkte für neue Buchungen und Leistungen.',
        'presence' => 'Auftritt des Betriebs',
        'presence_hint' => 'Wo Ihre Kundschaft Sie außerhalb von StyleDesk findet.',
        'advanced' => 'Weitere Angaben',
        'payments' => 'Zahlungen annehmen',
        'payments_hint' => 'Die Konten, an die Kundschaft überweisen soll. Sie werden an der Kasse vorgelesen und deshalb genau so behalten, wie Sie sie schreiben.',
    ],

    'fields' => [
        'name' => 'Name des Betriebs',
        'legal_name' => 'Firmierung',
        'business_type' => 'Art des Betriebs',
        'category' => 'Kategorie / Spezialisierung',
        'description' => 'Beschreibung',
        'logo' => 'Logo des Betriebs',
        'status' => 'Status',

        'business_email' => 'Haupt-E-Mail',
        'business_phone' => 'Haupttelefon',
        'support_email' => 'E-Mail für Anliegen',
        'booking_email' => 'E-Mail für Buchungsanfragen',
        'website' => 'Website',
        'website_scheme' => 'URL-Schema',

        'address_line1' => 'Adresszeile 1',
        'address_line2' => 'Adresszeile 2',
        'city' => 'Stadt',
        'state' => 'Bundesland / Region',
        'postal_code' => 'Postleitzahl',
        'country' => 'Land',
        'address' => 'Adresse',

        'primary_language' => 'Primäre Sprache',
        'secondary_languages' => 'Weitere Sprachen',
        'primary_currency' => 'Hauptwährung',
        'secondary_currencies' => 'Weitere Währungen',
        'timezone' => 'Zeitzone',
        'date_format' => 'Datumsformat',
        'time_format' => 'Zeitformat',
        'first_day_of_week' => 'Erster Wochentag',

        'default_location' => 'Standardstandort',
        'default_booking_duration' => 'Standarddauer einer Buchung',
        'default_appointment_interval' => 'Standardintervall für Termine',
        'default_tax_behavior' => 'Standard-Steuerbehandlung',
        'default_tax_rate' => 'Steuersatz',
        'paypal_handle' => 'PayPal',
        'zelle_handle' => 'Zelle',
        'cash_app_handle' => 'Cash App',
        'venmo_handle' => 'Venmo',
        'session_timeout' => 'Sitzungsende',
        'default_staff_assignment' => 'Standardzuweisung',
        'allow_online_booking' => 'Onlinebuchung erlauben',
        'guest_booking' => 'Buchung ohne Konto aktiviert',

        'business_id' => 'Betriebsnummer',
        'booking_address' => 'Adresse der Buchungsseite',

        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google_business' => 'Google-Unternehmensprofil',
    ],

    'manage' => 'Verwalten',
    'manage_branding' => 'Markenauftritt →',
    'enabled' => 'Aktiviert',
    'disabled' => 'Deaktiviert',

    /*
     * Die Validierungsmeldungen.
     *
     * Als Anweisung geschrieben, nicht als Beschreibung einer Regel. „Das Feld
     * Geschäfts-E-Mail muss eine gültige E-Mail-Adresse sein“ benennt den
     * Validator; „Geben Sie eine gültige E-Mail-Adresse ein“ benennt, was zu
     * tun ist — und es wird wenige Zentimeter neben dem Feld gelesen.
     */
    'validation' => [
        'name_required' => 'Der Name des Betriebs ist erforderlich.',
        'email_required' => 'Die Haupt-E-Mail ist erforderlich.',
        'email_invalid' => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'url_invalid' => 'Geben Sie eine gültige Webadresse ein, samt https://',
        'instagram_invalid' => 'Geben Sie eine gültige Instagram-Adresse ein, samt https://',
        'facebook_invalid' => 'Geben Sie eine gültige Facebook-Adresse ein, samt https://',
        'tiktok_invalid' => 'Geben Sie eine gültige TikTok-Adresse ein, samt https://',
        'google_invalid' => 'Geben Sie eine gültige Google-Business-Adresse ein, samt https://',
        'status_required' => 'Geben Sie an, ob der Betrieb aktiv ist.',
        'location_invalid' => 'Wählen Sie einen Ihrer eigenen Standorte.',
    ],

    /*
     * Die Werte in den Auswahlfeldern, nicht nur deren Bezeichnungen.
     *
     * §„Dieses Vorgehen gilt auch für die Werte in Auswahlfeldern“ — ein
     * Formular mit spanischen Bezeichnungen und englischen Optionen ist
     * dieselbe halb übersetzte Ansicht, nur eine Ebene tiefer.
     *
     * Die Namen der Datums- und Zeit*formate* bleiben, wie sie sind:
     * „DD/MM/YYYY“ ist ein Muster, kein Satz — die Buchstaben zu übersetzen
     * beschriebe ein Format, das niemand verwendet.
     */
    'first_day_of_week' => [
        '0' => 'Sonntag',
        '1' => 'Montag',
        '6' => 'Samstag',
    ],

    'time_formats' => [
        '12' => '12-Stunden (1:30 PM)',
        '24' => '24-Stunden (13:30)',
    ],

    'durations' => [
        'minutes' => '{1} :count Minute|[2,*] :count Minuten',
        'hour' => '1 Stunde',
        'hour_thirty' => '1 Stunde 30 Minuten',
        'hours' => ':count Stunden',
    ],

    'tax_behaviors' => [
        'inclusive' => 'Preise inklusive Steuer',
        'exclusive' => 'Steuer wird an der Kasse ergänzt',
        'none' => 'Keine Steuer',
    ],

    'staff_assignment' => [
        'any' => 'Wer gerade frei ist',
        'client-chooses' => 'Die Kundschaft wählt die Fachkraft',
        'manual' => 'Vom Betrieb manuell zugewiesen',
    ],

    'hints' => [
        'tax_rate' => 'Ein Prozentsatz. Wird auf Buchungssummen mit Steuer angewendet.',
        'inactive' => 'Ein inaktiver Betrieb erscheint nicht in der öffentlichen Buchung.',
        'session_timeout' => 'Meldet Nutzende nach einer Zeit ohne Aktivität automatisch ab.',
        'interval' => 'Das Raster, an dem Startzeiten einrasten.',
        'regional' => 'Sprachen, Währungen und Zeitzone werden in eigenen Modulen gesetzt.',
    ],

    'placeholders' => [
        'legal_name' => 'Wie eingetragen, falls abweichend vom Namen im Geschäftsverkehr',
        'category' => 'Spezialisiert auf Locken',
        'description' => 'Ein Satz, den Ihre Kundschaft auf der Buchungsseite liest.',
        'paypal_handle' => 'paypal.me/ihrsalon',
        'zelle_handle' => 'zahlung@ihrsalon.de',
        'cash_app_handle' => '$ihrsalon',
        'venmo_handle' => '@ihrsalon',
    ],

    'choose' => [
        'date_format' => 'Datumsformat wählen',
        'time_format' => 'Zeitformat wählen',
        'day' => 'Tag wählen',
        'duration' => 'Dauer wählen',
        'interval' => 'Intervall wählen',
        'tax' => 'Steuerbehandlung wählen',
        'session_timeout' => 'Zeitspanne wählen',
        'assignment' => 'Zuweisungsregel wählen',
    ],

    /*
     * Die Liste der Betriebsarten.
     *
     * StyleDesks eigene Stammdaten, nach dem Slug indexiert, den der Seeder
     * vergibt — nichts, was ein Betrieb selbst getippt hat, also unsere Sache
     * zu übersetzen. Alles, was ein Betrieb selbst geschrieben hat, bleibt in
     * seinen eigenen Worten.
     *
     * Der Name aus der Datenbank ist der Rückfall, damit eine später ergänzte
     * Art ohne Übersetzung als sie selbst erscheint.
     */
    'types' => [
        'hair-salon' => 'Friseursalon',
        'barber-shop' => 'Barbershop',
        'nail-salon' => 'Nagelstudio',
        'spa' => 'Spa',
        'massage' => 'Massage',
        'med-spa' => 'Med Spa',
        'esthetics' => 'Kosmetik',
        'eyebrows-lashes' => 'Brauen und Wimpern',
        'makeup-studio' => 'Make-up-Studio',
        'tattoo-studio' => 'Tattoostudio',
        'wellness' => 'Wellness',
        'fitness' => 'Fitness',
        'other' => 'Sonstiges',
    ],

    'session_timeouts' => [
        15 => '15 Minuten',
        30 => '30 Minuten (empfohlen)',
        60 => '1 Stunde',
        120 => '2 Stunden',
        240 => '4 Stunden',
        480 => '8 Stunden',
    ],
];
