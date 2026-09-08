<?php

declare(strict_types=1);

/*
| Die Symbolleiste, die Schublade und das Kontomenü.
|
| Das ist eine Übersetzungsschicht, keine Umbenennung: Ein abweichender Wert
| würde die Produkttexte ändern, getarnt als das Hinzufügen einer Sprache.
|
| Die Schlüssel spiegeln den `key` aus config/navigation.php — Nav::label() löst
| navigation.<key> auf, die beiden können also nicht auseinanderlaufen.
*/

return [
    'bookings' => 'Buchungen',
    'menu_for' => 'Menü :name',
    'dashboard' => 'Übersicht',
    'calendar' => 'Kalender',
    'clients' => 'Kunden',
    'services' => 'Leistungen und Ressourcen',
    'staff' => 'Personal',
    'sales' => 'Umsatz',
    'marketing' => 'Marketing',
    'reports' => 'Berichte',
    'mentions' => 'Erwähnungen',
    'activity' => 'Aktivität',
    'design-system' => 'Designsystem',
    'team' => 'Team',
    'app_settings' => 'Einstellungen',
    'main_menu' => 'Hauptmenü',
    'search_placeholder' => 'Tippen, um zu suchen und zuletzt Verwendetes zu sehen…',
    'my_profile' => 'Mein Profil',
    'sign_out' => 'Abmelden',
    'invite_team_members' => 'Team einladen',
    'add' => 'Hinzufügen',

    'active_staff' => '{0} Keine aktiven Mitarbeitenden|{1} :count aktive Person|[2,*] :count aktive Personen',
    'coming_soon' => 'Demnächst',

    /*
    | Die Untereinträge unter den obersten Punkten der Leiste.
    |
    | Jeder trägt in config/navigation.php einen `key`, damit Nav::label() ihn
    | hier auflösen kann — auch die, deren Seiten noch nicht gebaut sind. Sie
    | stehen im Menü und werden gelesen, also ist ein halb übersetztes Menü
    | derselbe Fehler, ganz gleich ob der Link irgendwohin führt.
    */
    'all_bookings' => 'Alle Buchungen',
    'booking_leads' => 'Buchungsanfragen',
    'add_booking' => '+ Buchung anlegen',
    'add_walkin' => '+ Laufkundschaft anlegen',
    'all_clients' => 'Alle Kunden',
    'add_client' => 'Kunden anlegen',
    'coupons_offers' => 'Gutscheine und Angebote',
    'all_services' => 'Alle Leistungen',
    'add_service' => 'Leistung anlegen',
    'all_resources' => 'Alle Ressourcen',
    'add_resource' => 'Ressource anlegen',
    'resource_availability' => 'Ressourcenverfügbarkeit',
    'resource_utilization' => 'Ressourcenauslastung',
    'all_staff' => 'Gesamtes Personal',
    'staff_schedule' => 'Dienstplan',
    'shifts' => 'Schichten',
    'staff_utilization' => 'Personalauslastung',
    'add_staff' => '+ Mitarbeitende anlegen',
    'email_marketing' => 'E-Mail-Marketing',
    'sms_marketing' => 'SMS-Marketing',
    'social_marketing' => 'Social-Media-Marketing',
    'review_marketing' => 'Google-Bewertungsmarketing',
    'gift_cards' => 'Geschenkgutscheine',
    'loyalty' => 'Treueprogramm',
    'groups' => 'Gruppen',
    'forms_waivers' => 'Formulare und Einverständnisse',
    'memberships_packages' => 'Mitgliedschaften und Pakete',

    /* Die Überschriften über einer Gruppe von Einträgen in einem offenen Menü. */
    'sections' => [
        'management' => 'Verwaltung',
        'quick_actions' => 'Schnellaktionen',
        'services' => 'Leistungen',
        'resources' => 'Ressourcen',
        'channels' => 'Kanäle',
    ],

    /* Barrierefreie Namen, wo sie von der Bezeichnung daneben abweichen. */
    'aria' => [
        'services' => 'Leistungen und Ressourcen',
    ],

    /* Überschriften, die nur ein Screenreader erreicht. */
    'drawer_main' => 'Hauptbereich',
    'rail_primary' => 'Hauptbereich',
];
