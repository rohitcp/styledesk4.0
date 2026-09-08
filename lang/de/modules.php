<?php

declare(strict_types=1);

/*
| Die Startseite der Einstellungen: Gruppenüberschriften und Modulkarten.
|
| Jeder englische Wert hier ist die Zeichenkette, die config/app_settings.php
| ohnehin schon trug. Das ist eine Übersetzungsschicht, keine Neufassung — ein
| abweichender Wert würde die Produkttexte ändern, getarnt als das Hinzufügen
| einer Sprache.
|
| Die Config behält ihre eigenen Literale als letzten Rückfall, damit ein ohne
| Übersetzung ergänztes Modul seinen eigenen Namen zeigt statt eines
| Schlüssels.
*/

return [
    'groups' => [
        'business_setup' => [
            'name' => 'Betrieb einrichten',
            'description' => 'Wer Sie sind, wo Sie arbeiten und wie Sie den Betrieb darstellen.',
        ],
        'booking_operations' => [
            'name' => 'Buchung und Abläufe',
            'description' => 'Die Regeln, die entscheiden, was von wem und wann gebucht werden kann.',
        ],
        'clients_experience' => [
            'name' => 'Kundschaft und Erlebnis',
            'description' => 'Was Ihre Kundschaft sieht, ausfüllt und kauft.',
        ],
        'communication' => [
            'name' => 'Kommunikation',
            'description' => 'Was StyleDesk versendet, an wen, und wie es klingt.',
        ],
        'finance' => [
            'name' => 'Finanzen',
            'description' => 'Geld annehmen und alles, was daraus folgt.',
        ],
        'administration' => [
            'name' => 'Verwaltung',
            'description' => 'Wer was darf, und was mit Ihren Daten geschieht.',
        ],
        'developer_integrations' => [
            'name' => 'Entwicklung und Integrationen',
            'description' => 'StyleDesk mit allem anderen verbinden.',
        ],
    ],

    'modules' => [
        'business' => [
            'name' => 'Betrieb',
            'description' => 'Name, Art, Kontaktdaten und Betriebskonfiguration.',
        ],
        'locations' => [
            'name' => 'Standorte',
            'description' => 'Filialen, Adressen, Standortleitung, Öffnungszeiten und Kontaktdaten.',
        ],
        'business-hours' => [
            'name' => 'Öffnungszeiten',
            'description' => 'Öffnungs- und Schließzeiten, geteilte Dienste, Feiertage und vorübergehende Schließungen.',
        ],
        'branding' => [
            'name' => 'Markenauftritt',
            'description' => 'Logo, Favicon und Markenfarben in App, E-Mails und Belegen.',
        ],
        'languages' => [
            'name' => 'Sprachen',
            'description' => 'Legen Sie die primäre Sprache der Anwendung fest und wählen Sie, welche weiteren Sprachen Ihrem Team zur Verfügung stehen.',
        ],
        'currency' => [
            'name' => 'Währung',
            'description' => 'Ihre Hauptwährung und die weiteren Währungen, in denen Sie Preise angeben.',
        ],
        'booking-rules' => [
            'name' => 'Buchungsregeln',
            'description' => 'Intervalle, Vorlaufzeiten, Buchungsfenster und Terminregeln.',
        ],
        'services' => [
            'name' => 'Leistungskategorien',
            'description' => 'Die Kategorien, in die Ihre Preisliste gegliedert ist — welche angeboten werden und in welcher Reihenfolge sie erscheinen.',
        ],
        'resources' => [
            'name' => 'Ressourcenkategorien',
            'description' => 'Die Kategorien, in die Ihre buchbaren Betriebsmittel gruppiert sind — Stühle, Räume, Geräte — samt Auswahl und Reihenfolge.',
        ],
        'staff' => [
            'name' => 'Mitarbeitende',
            'description' => 'Teammitglieder, Standorte, Arbeitszeiten, Zugriff auf Leistungen und Beschäftigungsstatus.',
        ],
        'calendar-scheduling' => [
            'name' => 'Kalender und Planung',
            'description' => 'Kalenderverhalten, Planungsvorgaben und wie Termine dargestellt werden.',
        ],
        'cancellation-no-show' => [
            'name' => 'Absagen und Nichterscheinen',
            'description' => 'Stornoregeln und -fristen, Gebühren und der Umgang mit Nichterscheinen.',
        ],
        'clients' => [
            'name' => 'Kundschaft',
            'description' => 'Voreinstellungen, Präferenzen und wie Kundendatensätze aufgebaut sind.',
        ],
        'client-booking' => [
            'name' => 'Buchung durch Kundschaft',
            'description' => 'Das Buchungserlebnis auf Kundenseite und was Kundschaft selbst erledigen darf.',
        ],
        'online-booking' => [
            'name' => 'Onlinebuchung',
            'description' => 'Öffentliche Verfügbarkeit, Verhalten der Buchungsseite und Regeln der Onlinebuchung.',
        ],
        'forms' => [
            'name' => 'Formulare',
            'description' => 'Aufnahme-, Einwilligungs- und Beratungsformulare — und wann Kundschaft sie ausfüllen soll.',
        ],
        'memberships' => [
            'name' => 'Mitgliedschaften',
            'description' => 'Mitgliedsstufen, wiederkehrende Vorteile und wie Mitgliedschaften angewendet werden.',
        ],
        'packages' => [
            'name' => 'Pakete',
            'description' => 'Gebündelte Leistungen, wie Pakete verkauft und Einheiten abgebucht werden.',
        ],
        'gift-cards' => [
            'name' => 'Geschenkgutscheine',
            'description' => 'Gutscheinwerte, Gültigkeit, Einlöseregeln und Voreinstellungen.',
        ],
        'loyalty-rewards' => [
            'name' => 'Treue und Prämien',
            'description' => 'Punkte, Prämien, Sammelregeln und wie Kundschaft sie einlöst.',
        ],
        'notifications' => [
            'name' => 'Benachrichtigungen',
            'description' => 'Verhalten von E-Mail- und In-App-Benachrichtigungen, und welches Ereignis wen benachrichtigt.',
        ],
        'email-settings' => [
            'name' => 'E-Mail-Einstellungen',
            'description' => 'Absendername und -adresse, Antwortadresse und E-Mail-Voreinstellungen.',
        ],
        'email-templates' => [
            'name' => 'E-Mail-Vorlagen',
            'description' => 'Bestätigungen, Erinnerungen, Absagen, Verschiebungen, Einladungen und Willkommens-E-Mails.',
        ],
        'sms-settings' => [
            'name' => 'SMS-Einstellungen',
            'description' => 'SMS-Absender, Standardtexte und wann Kurznachrichten versendet werden.',
        ],
        'payments' => [
            'name' => 'Zahlungen',
            'description' => 'Akzeptierte Zahlungsarten, Anzahlungen, Zahlungsverhalten und Voreinstellungen.',
        ],
        'taxes' => [
            'name' => 'Steuern',
            'description' => 'Steuersätze, worauf sie sich beziehen, und steuerliche Voreinstellungen.',
        ],
        'tips' => [
            'name' => 'Trinkgeld',
            'description' => 'Trinkgeldoptionen, vorgeschlagene Prozentsätze und wie Trinkgeld aufgeteilt wird.',
        ],
        'receipts-invoices' => [
            'name' => 'Belege und Rechnungen',
            'description' => 'Nummerierung, Format und was auf Belegen und Rechnungen erscheint.',
        ],
        'inventory' => [
            'name' => 'Bestand',
            'description' => 'Bestandsvorgaben, Verhalten bei niedrigem Bestand und Einstellungen für Verkaufsprodukte.',
        ],
        'roles-permissions' => [
            'name' => 'Rollen und Rechte',
            'description' => 'Legen Sie fest, worauf Inhaber, Admins, Leitungen, Empfang, Fachkräfte und eigene Rollen zugreifen dürfen.',
        ],
        'security' => [
            'name' => 'Sicherheit',
            'description' => 'Sitzungsverhalten, Anmelderichtlinien und Sicherheitseinstellungen der Anwendung.',
        ],
        'data-privacy' => [
            'name' => 'Daten und Datenschutz',
            'description' => 'Aufbewahrung, Einwilligungen der Kundschaft und Datenschutzkonfiguration.',
        ],
        'import-export' => [
            'name' => 'Import und Export',
            'description' => 'Daten hereinholen, Daten herausgeben und Migrationen durchführen.',
        ],
        'system-preferences' => [
            'name' => 'Systemeinstellungen',
            'description' => 'Allgemeines Verhalten von StyleDesk und anwendungsweite Voreinstellungen.',
        ],
        'integrations' => [
            'name' => 'Integrationen',
            'description' => 'Integrationen von Drittanbietern und verbundene Dienste.',
        ],
        'api-webhooks' => [
            'name' => 'API und Webhooks',
            'description' => 'API-Zugriff, Schlüssel, Webhook-Endpunkte und Entwicklerintegrationen.',
        ],
    ],

    /*
     * Die Statusabzeichen der Module.
     *
     * Nach dem Status aus config/app_settings.php verschlüsselt, damit ein
     * neuer Status ein Schlüssel hier ist und keine Verzweigung in einer
     * Ansicht.
     */
    'statuses' => [
        'active' => 'Aktiv',
        'setup-required' => 'Einrichtung erforderlich',
        'coming-soon' => 'Demnächst',
        'view-only' => 'Nur Ansicht',
    ],

    /*
     * Die Zahlen unter einer Karte.
     *
     * Mit Laravels |-Syntax pluralisiert statt über Str::plural(), das nur
     * Englisch kann. „1 Sprache|Sprachen“ ist im Deutschen dieselbe Aussage
     * und lässt sich aus dem Englischen nicht ableiten.
     */
    'counts' => [
        'active_staff' => '{1} aktive Person|[2,*] aktive Personen',
        'pending_invites' => '{1} offene Einladung|[2,*] offene Einladungen',
        'roles' => '{1} Rolle|[2,*] Rollen',
        'active_locations' => '{1} aktiver Standort|[2,*] aktive Standorte',
        'upcoming_closures' => '{1} anstehende Schließung|[2,*] anstehende Schließungen',
        'enabled_currencies' => '{1} Währung|[2,*] Währungen',
        'enabled_languages' => '{1} Sprache|[2,*] Sprachen',
    ],
];
