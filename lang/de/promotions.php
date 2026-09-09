<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gutscheine und Angebote
|--------------------------------------------------------------------------
|
| Ein Modul für beides, denn es ist dieselbe Sache mit einem Unterschied: Ein
| Gutschein wird eingetippt, ein Angebot wendet sich selbst an.
|
*/

return [

    'title' => 'Gutscheine und Angebote',
    'intro' => 'Rabatte, die jemand eintippt, und solche, die sich selbst anwenden.',
    'new' => 'Gutschein / Angebot anlegen',
    'none_yet' => 'Noch keine Gutscheine oder Angebote.',
    'none_yet_hint' => 'Ein Rabatt für den ersten Besuch ist das, womit die meisten Salons beginnen.',
    'automatic' => 'Automatisch',
    'no_expiry_short' => 'Ohne Ablauf',
    'copy_of' => ':name (Kopie)',

    'summary' => [
        'active' => 'Laufende Angebote',
        'scheduled' => 'Geplant',
        'expired' => 'Abgelaufen',
        'redemptions' => 'Einlösungen gesamt',
    ],

    'columns' => [
        'name' => 'Name',
        'code' => 'Code',
        'type' => 'Typ',
        'discount' => 'Rabatt',
        'applies' => 'Gilt für',
        'starts' => 'Beginn',
        'ends' => 'Ende',
        'used' => 'Genutzt',
        'status' => 'Status',
    ],

    'types' => [
        'coupon' => 'Gutschein',
        'offer' => 'Angebot',
    ],

    'statuses' => [
        'draft' => 'Entwurf',
        'scheduled' => 'Geplant',
        'active' => 'Läuft',
        'expired' => 'Abgelaufen',
        'disabled' => 'Deaktiviert',
    ],

    'applies' => [
        'booking' => 'Gesamte Buchung',
        'all_services' => 'Alle Leistungen',
        'services' => 'Ausgewählte Leistungen',
        'categories' => 'Ausgewählte Kategorien',
    ],

    'filters' => [
        'all_statuses' => 'Alle Status',
        'all_types' => 'Alle Typen',
        'all_locations' => 'Alle Standorte',
        'reset' => 'Zurücksetzen',
    ],

    'search' => 'Nach Name, Code oder Leistung suchen…',
    'results' => [
        'zero' => 'Keine Gutscheine oder Angebote passen',
        'one' => '1 Gutschein oder Angebot',
        'many' => ':count Gutscheine und Angebote',
        'clear' => 'Filter zurücksetzen',
    ],
    'showing' => ':from–:to von :total werden angezeigt',
    'empty' => 'Nichts passt zu diesen Filtern.',
    'actions_for' => 'Aktionen für :name',

    /* -------------------------------------------------------------- Formular */

    'form' => [
        'create_title' => 'Gutschein oder Angebot anlegen',
        'edit_title' => 'Gutschein oder Angebot bearbeiten',

        'templates' => 'Mit einer Vorlage beginnen',
        'templates_hint' => 'Eine Vorlage füllt nur das Formular aus. Alles, was sie setzt, lässt sich vor dem Speichern ändern.',
        'scratch' => 'Bei null beginnen',

        'basics' => 'Basisangaben',
        'name' => 'Name der Aktion',
        'name_placeholder' => 'Neukunde 20 % Rabatt',
        'description' => 'Interne Beschreibung',
        'description_hint' => 'Für das Team, nicht für die Kundschaft.',
        'type' => 'Art der Aktion',
        'type_coupon' => 'Gutscheincode',
        'type_coupon_hint' => 'Die Kundschaft oder der Empfang tippt ihn ein.',
        'type_offer' => 'Automatisches Angebot',
        'type_offer_hint' => 'Wendet sich selbst auf jede passende Buchung an.',
        'code' => 'Gutscheincode',
        'code_hint' => 'Buchstaben, Ziffern und Bindestriche. Wird in Großbuchstaben gespeichert.',
        'generate' => 'Erzeugen',

        'discount' => 'Rabatt',
        'discount_type' => 'Art des Rabatts',
        'percent' => 'Prozentsatz',
        'fixed' => 'Fester Betrag',
        'amount' => 'Betrag',
        'percent_hint' => 'Ein Prozentsatz des betroffenen Teils. Bis 100.',
        'fixed_hint' => 'Ein fester Betrag weniger, nie mehr als der betroffene Teil.',

        'applies' => 'Gilt für',
        'applies_hint' => 'Ein Prozentsatz wird vom betroffenen Teil abgezogen, nicht von der ganzen Rechnung.',
        'services' => 'Leistungen',
        'categories' => 'Kategorien',

        'locations' => 'Standorte',
        'all_locations' => 'Alle Standorte',
        'selected_locations' => 'Ausgewählte Standorte',

        'validity' => 'Gültigkeit',
        'starts' => 'Startdatum',
        'ends' => 'Enddatum',
        'no_expiry' => 'Kein Ablaufdatum',
        'days' => 'Gültige Tage',
        'days_hint' => 'Lassen Sie alle Tage angehakt, außer die Aktion gilt bestimmten Tagen — ein leerer Dienstag lässt sich am Mittwoch nicht noch einmal verkaufen.',

        'eligibility' => 'Für wen',
        'eligibility_all' => 'Gesamte Kundschaft',
        'eligibility_new' => 'Nur Neukundschaft',
        'eligibility_new_hint' => 'Für sie wurde noch kein Termin abgeschlossen.',
        'eligibility_existing' => 'Nur bestehende Kundschaft',
        'eligibility_selected' => 'Ausgewählte Kundschaft',
        'clients' => 'Kundschaft',

        'redemption' => 'Einlöseregeln',
        'min_spend' => 'Mindestbetrag der Buchung',
        'min_spend_hint' => 'Optional. Verhindert, dass ein fester Rabatt auf eine sehr kleine Buchung angewendet wird.',
        'total_limit' => 'Einlösungen insgesamt',
        'total_limit_hint' => 'Leer lassen für unbegrenzt.',
        'per_client_limit' => 'Je Person',
        'per_client_limit_hint' => 'Leer lassen für unbegrenzt. Einmal pro Person ist die übliche Antwort.',

        'availability' => 'Wo es genutzt werden kann',
        'allow_online' => 'Kundschaft darf das bei der Onlinebuchung nutzen',
        'combinable' => 'Mit einer anderen Aktion kombinierbar',
        'combinable_hint' => 'Aus ist die sichere Antwort: eine Aktion je Buchung.',
        'draft' => 'Als Entwurf speichern',
        'draft_hint' => 'Ein Entwurf wird niemandem angeboten, bis Sie das abschalten.',

        'save' => 'Speichern',
        'cancel' => 'Abbrechen',
    ],

    'weekdays' => [
        0 => 'Sonntag',
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
    ],

    /* ------------------------------------------------------------ Details */

    'details' => [
        'title' => 'Details des Angebots',
        'discount' => 'Rabatt',
        'for' => 'Verfügbar für',
        'services' => 'Leistungen',
        'locations' => 'Standorte',
        'valid' => 'Gültig',
        'usage' => 'Nutzung',
        'online' => 'Onlinebuchung',
        'online_yes' => 'Kundschaft kann es selbst nutzen',
        'online_no' => 'Nur Personal',
        'created_by' => 'Angelegt von :name',
        'no_expiry' => 'Ohne Ablauf',
        'every_day' => 'Jeden Tag',
    ],

    'report' => [
        'title' => 'Wie es läuft',
        'redemptions' => 'Einlösungen',
        'clients' => 'Personen',
        'discount' => 'Gewährter Rabatt',
        'revenue' => 'Umsatz dieser Buchungen',
        'revenue_hint' => 'Was die Buchungen ergaben, bei denen es genutzt wurde — keine Behauptung, dass die Aktion sie ausgelöst hat.',
        'none' => 'Bisher hat es niemand genutzt.',
    ],

    'actions' => [
        'view' => 'Ansehen',
        'edit' => 'Bearbeiten',
        'duplicate' => 'Duplizieren',
        'disable' => 'Deaktivieren',
        'enable' => 'Aktivieren',
        'back' => 'Gutscheine und Angebote',
    ],

    /* --------------------------------------------------------- die Antwort */

    'refused' => [
        'not_running' => 'Diese Aktion läuft nicht.',
        'not_started' => 'Diese Aktion hat noch nicht begonnen.',
        'expired' => 'Diese Aktion ist abgelaufen.',
        'wrong_day' => 'Diese Aktion gilt an diesem Tag nicht.',
        'wrong_location' => 'Diese Aktion ist an diesem Standort nicht verfügbar.',
        'needs_a_client' => 'Diese Aktion gilt nur bestimmten Personen, die Buchung braucht also eine.',
        'new_only' => 'Diese Aktion gilt nur für Neukundschaft.',
        'existing_only' => 'Diese Aktion gilt nur für bestehende Kundschaft.',
        'not_for_this_client' => 'Diese Aktion ist für diese Person nicht verfügbar.',
        'no_eligible_services' => 'Nichts in dieser Buchung kommt für diese Aktion infrage.',
        'under_minimum' => 'Diese Aktion verlangt eine Buchung von mindestens :amount.',
        'fully_redeemed' => 'Diese Aktion ist vollständig eingelöst.',
        'client_limit' => 'Diese Person hat die Aktion bereits genutzt.',
        'unknown_code' => 'Keine Aktion mit diesem Code.',
    ],

    'created' => 'Gutschein oder Angebot angelegt.',
    'saved' => 'Gutschein oder Angebot gespeichert.',
    'duplicated' => 'Kopiert. Es ist als Entwurf gespeichert.',
    'disabled' => 'Aktion deaktiviert.',
    'enabled' => 'Aktion aktiviert.',
];
