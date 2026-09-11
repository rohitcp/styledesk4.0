<?php

declare(strict_types=1);

/*
| Das Standortmodul: die Kartenübersicht, die Detailansicht und das Formular
| zum Anlegen und Bearbeiten.
|
| Die Feldbezeichnungen sind bewusst für alle drei dieselben. Wenn
| „Standortkürzel“ auf einer Karte das eine und im Formular das andere
| benennt, ist das genau das Auseinanderdriften, dem ein gemeinsamer Schlüssel
| vorbeugt.
*/

return [
    'title' => 'Standorte',
    'intro' => 'Öffnen Sie einen Standort für Leitung, Öffnungszeiten, Leistungen, Personal und Buchungseinstellungen.',
    'summary' => '{1} :count aktiver Standort|[2,*] :count aktive Standorte',
    'summary_inactive' => ':count inaktiv',

    'add' => 'Standort anlegen',
    'add_first' => 'Legen Sie Ihren ersten Standort an',
    'add_title' => 'Standort anlegen',
    'add_intro' => 'Adresse, Kontaktdaten, Leitung und Öffnungszeiten der Filiale. Leistungen, Personalzuordnung und Buchungsregeln werden eingerichtet, sobald der Standort besteht.',
    'edit' => 'Standort bearbeiten',
    'edit_title' => 'Standort bearbeiten',
    'view' => 'Standort ansehen',
    'deactivate' => 'Standort deaktivieren',
    'activate' => 'Standort aktivieren',

    'created' => 'Standort angelegt.',
    'saved' => 'Standort gespeichert.',
    'save_failed' => 'Ihre Änderungen konnten nicht gespeichert werden. Bitte prüfen Sie die Angaben und versuchen Sie es erneut.',
    'correct_fields' => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',
    'made_inactive' => ':name ist jetzt inaktiv. Es nimmt keine neuen Buchungen an; die Historie bleibt unverändert.',
    'made_active' => ':name ist wieder aktiv.',

    'search_placeholder' => 'Nach Name, Kürzel, Stadt oder Adresse suchen',
    'search_label' => 'Standorte durchsuchen',
    'all_statuses' => 'Alle Status',
    'actions_for' => 'Aktionen für :name',

    'empty_title' => 'Noch keine Standorte.',
    'empty_body' => 'Legen Sie die Filiale an, von der aus Sie arbeiten — dann haben Personal, Leistungen und Buchungen eine Zugehörigkeit.',
    'no_matches' => 'Kein Standort passt zu Ihrer Suche.',
    'no_matches_hint' => 'Versuchen Sie ein anderes Wort oder setzen Sie die Filter zurück.',
    'clear_search' => 'Suche leeren',

    'inactive_notice' => 'Dieser Standort ist inaktiv. Er nimmt keine neuen Buchungen an und erscheint nicht in der Onlinebuchung. Vergangene Termine, Umsätze und die Personalhistorie bleiben unverändert.',

    'cards' => [
        'information' => 'Standortangaben',
        'address' => 'Adresse',
        'contact' => 'Kontaktdaten',
        'contact_hint' => 'Werden überall dort statt der Hauptkontaktdaten verwendet, wo diese Filiale genannt wird.',
        'manager' => 'Standortleitung',
        'manager_hint' => 'Wer für diese Filiale verantwortlich ist. Jemanden hier zu benennen ändert nicht, was er in StyleDesk darf — das entscheidet seine Rolle unter Rollen und Rechte.',
        'manager_hint_short' => 'Wer für diese Filiale verantwortlich ist. Das ändert nicht, was die Person in StyleDesk darf.',
        'hours' => 'Öffnungszeiten',
        'hours_hint' => 'Die Zeiten gelten in der eigenen Zeitzone dieses Standorts, :timezone.',
        'elsewhere' => 'Woanders eingerichtet',
        'elsewhere_hint' => 'Diese folgen den betriebsweiten Einstellungen, bis es Einstellungen je Standort gibt.',
    ],

    'fields' => [
        'name' => 'Name des Standorts',
        'code' => 'Standortkürzel',
        'code_hint' => 'Ein kurzer Name, der diese Filiale auf Berichten und Belegen unterscheidet.',
        'type' => 'Art des Standorts',
        'primary' => 'Hauptstandort',
        'primary_hint' => 'Die Hauptfiliale des Betriebs. Wenn Sie das hier setzen, verliert der bisherige Hauptstandort diese Rolle.',
        'status' => 'Status',
        'status_hint' => 'Ein inaktiver Standort nimmt keine neuen Buchungen an und erscheint nicht in der Onlinebuchung. Die Historie bleibt erhalten.',

        'address_line1' => 'Adresszeile 1',
        'address_line2' => 'Adresszeile 2',
        'suite' => 'Gebäude / Einheit',
        'city' => 'Stadt',
        'state' => 'Bundesland / Region',
        'postal_code' => 'Postleitzahl',
        'country' => 'Land',
        'timezone' => 'Zeitzone',
        'timezone_hint' => 'Öffnungszeiten und Buchungen dieser Filiale werden in dieser Zone gelesen.',

        'manager' => 'Standortleitung',
        'assistants' => 'Stellvertretungen',
        'assistants_hint' => 'Wer oben bereits als Standortleitung gewählt ist, erscheint nicht doppelt.',

        'phone' => 'Haupttelefonnummer',
        'phone_short' => 'Haupttelefon',
        'phone_secondary' => 'Zweite Telefonnummer',
        'email' => 'E-Mail des Standorts',
        'booking_email' => 'E-Mail für Buchungsanfragen',
        'support_email' => 'E-Mail für Kundenanliegen',
        'website' => 'Website',
        'extension' => 'Interne Durchwahl',
        'contact_person' => 'Ansprechperson',

        'contact' => 'Kontakt',
        'today' => 'Heute',
    ],

    'placeholders' => [
        'name' => 'Salon Innenstadt',
        'code' => 'DT01',
        'type' => 'Keine Angabe',
        'country' => 'Land wählen',
        'state' => 'Bundesland oder Region wählen',
        'timezone' => 'Zeitzone wählen',
        'manager' => 'Nicht zugewiesen',
    ],

    'not_assigned' => 'Nicht zugewiesen',
    'no_phone' => 'Keine Telefonnummer',
    'closed' => 'Geschlossen',
    'closed_today' => 'Heute geschlossen',
    'no_active_staff' => 'Noch keine aktiven Mitarbeitenden.',
    'no_active_staff_link' => 'Mitarbeitende anlegen',
    'no_active_staff_tail' => 'und Sie können hier eine Leitung benennen.',

    'elsewhere' => [
        'holidays' => 'Feiertage und Sonderzeiten',
        'holidays_value' => 'Folgt den Öffnungszeiten',
        'staff' => 'Zugeordnetes Personal',
        'staff_value' => '{1} :count Person hat dies als Hauptstandort|[2,*] :count Personen haben dies als Hauptstandort',
        'services' => 'Angebotene Leistungen',
        'services_value' => 'Alle Leistungen des Betriebs',
        'resources' => 'Ressourcen und Räume',
        'resources_value' => 'Noch nicht eingerichtet',
        'booking' => 'Buchungseinstellungen',
        'currency' => 'Währung und Sprache',
        'uses_business' => 'Verwendet die Betriebseinstellungen',
        'link_hours' => 'Öffnungszeiten →',
        'link_staff' => 'Personal →',
        'link_services' => 'Leistungen →',
        'link_business' => 'Betrieb →',
        'link_permissions' => 'Rechte →',
    ],

    'confirm' => [
        'deactivate' => ':name inaktiv setzen? Der Standort nimmt keine neuen Buchungen mehr an und verschwindet aus der Onlinebuchung. Vergangene Termine, Umsätze und die Personalhistorie bleiben erhalten.',
        'activate' => ':name wieder aktiv setzen? Der Standort kann dann buchen und erscheint in der Onlinebuchung.',
    ],

    'validation' => [
        'name_required' => 'Der Name des Standorts ist erforderlich.',
        'code_unique' => 'Ein anderer Standort verwendet dieses Kürzel bereits.',
        'status_required' => 'Geben Sie an, ob dieser Standort aktiv ist.',
        'address_required' => 'Adresszeile 1 ist erforderlich.',
        'city_required' => 'Die Stadt ist erforderlich.',
        'state_required' => 'Bundesland oder Region ist erforderlich.',
        'postal_required' => 'Die Postleitzahl ist erforderlich.',
        'country_required' => 'Wählen Sie ein Land.',
        'country_in' => 'Wählen Sie ein Land aus der Liste.',
        'timezone_required' => 'Wählen Sie eine Zeitzone.',
        'timezone_in' => 'Wählen Sie eine Zeitzone aus der Liste.',
        'phone_required' => 'Die Haupttelefonnummer ist erforderlich.',
        'email_required' => 'Die E-Mail des Standorts ist erforderlich.',
        'email_invalid' => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'url_invalid' => 'Geben Sie eine gültige Webadresse ein, samt https://',
        'closes_after_opens' => 'Die Schließzeit muss nach der Öffnungszeit liegen.',
        'staff_invalid' => 'Wählen Sie eine Ihrer eigenen Fachkräfte.',
    ],

    /*
     * Die Listen für Art und Status sowie die Wochentage.
     *
     * Die Schlüssel sind der in der Datenbank gespeicherte Wert, damit
     * Auswahlfeld und Validierungsregel dieselbe Liste lesen, während jede
     * Sprache nur entscheidet, wie die Optionen heißen.
     */
    'types' => [
        'salon' => 'Salon',
        'spa' => 'Spa',
        'barbershop' => 'Barbershop',
        'clinic' => 'Praxis',
        'studio' => 'Studio',
        'mobile' => 'Mobil',
        'other' => 'Sonstiges',
    ],

    'statuses' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
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

    'hours_card' => 'Öffnungszeiten des Standorts',
    'hours_card_hint' => 'Wann diese Filiale geöffnet ist, in ihrer eigenen Zeitzone. Fügen Sie einem Tag mit Mittagspause eine zweite Zeitspanne hinzu.',

    /*
     * Die Bedienelemente der Öffnungszeiten-Insel.
     *
     * Als Props übergeben statt in der Komponente gelesen: Der Server kennt die
     * Sprache der lesenden Person, und eine Vue-Insel mit einer eigenen Kopie
     * jeder Zeichenkette wäre ein zweiter Ort zum Übersetzen.
     */
    'hours_editor' => [
        'copy_monday' => 'Montag auf Di.–Fr. übertragen',
        'open' => 'Geöffnet',
        'closed' => 'Geschlossen',
        'closed_all_day' => 'Ganztägig geschlossen',
        'add_period' => 'Weitere Zeitspanne',
        'to' => 'bis',
        'remove_period' => 'Diese Zeitspanne von :day entfernen',
        'opening_time' => 'Öffnungszeit :day',
        'closing_time' => 'Schließzeit :day',
        'copied' => 'Die Zeiten vom Montag wurden auf Dienstag bis Freitag übertragen.',
    ],
];
