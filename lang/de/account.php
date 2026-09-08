<?php

declare(strict_types=1);

/* Mein Konto — die Einstellungen der angemeldeten Person selbst. Bewusst
   getrennt von der settings-Datei, die zu den App-Einstellungen gehört und den
   Betrieb konfiguriert statt die Person. */

return [
    'title' => 'Mein Konto',
    'intro' => 'Ihr eigenes Profil, Ihre Präferenzen und Ihre Sicherheit. Nichts davon ändert etwas für den Rest des Betriebs.',

    'sections' => [
        'profile' => 'Mein Profil',
        'preferences' => 'Meine Präferenzen',
        'password' => 'Passwort ändern',
        'notifications' => 'Benachrichtigungen',
    ],

    'save' => 'Änderungen speichern',
    'cancel' => 'Abbrechen',
    'reset' => 'Auf Standard zurücksetzen',
    'reset_confirm' => 'Diese auf die Voreinstellungen des Betriebs zurücksetzen?',

    'profile' => [
        'title' => 'Mein Profil',
        'intro' => 'Wie Sie in StyleDesk erscheinen und wie wir Sie erreichen.',

        'photo_card' => 'Profilbild',
        'photo_hint' => 'JPG, PNG oder WebP, bis 5 MB. Bis Sie eines hinzufügen, werden Ihre Initialen verwendet.',
        'photo_upload' => 'Foto hochladen',
        'photo_replace' => 'Foto ersetzen',
        'photo_remove' => 'Entfernen',
        'photo_saved' => 'Profilbild aktualisiert.',
        'photo_removed' => 'Profilbild entfernt.',
        'photo_too_large' => 'Dieses Bild ist größer als 5 MB.',
        'photo_wrong_type' => 'Wählen Sie ein JPG-, PNG- oder WebP-Bild.',
        'photo_pending' => 'Vorschau — zum Behalten speichern.',

        'personal_card' => 'Persönliche Angaben',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'display_name' => 'Anzeigename',
        'display_name_hint' => 'Leer lassen, um Vor- und Nachnamen zu verwenden.',
        'display_name_placeholder' => 'Wie Ihr Name für Kundschaft erscheint',
        'job_title' => 'Position',
        'job_title_placeholder' => 'Erste Kraft',
        'phone' => 'Mobilnummer',
        'phone_hint' => 'Wird für Terminhinweise genutzt, sobald SMS eingeschaltet sind.',
        'saved' => 'Profil aktualisiert.',

        'email_card' => 'E-Mail-Adresse',
        'email' => 'E-Mail-Adresse',
        'email_locked_hint' => 'Die Adresse, mit der Sie sich anmelden. Wenden Sie sich an eine Person mit Administrationsrechten, wenn sie sich ändern soll.',
        'email_hint' => 'Mit dieser Adresse melden Sie sich an. Sie zu ändern erfordert Ihr Passwort und eine Bestätigung von der neuen Adresse.',
        'email_current_password' => 'Aktuelles Passwort',
        'email_change_cta' => 'Ändern',
        'email_new' => 'Neue E-Mail-Adresse',
        'email_change' => 'E-Mail-Adresse ändern',
        'email_subject' => 'Bestätigen Sie Ihre neue StyleDesk-E-Mail-Adresse',
        'email_pending_title' => 'Bestätigen Sie Ihre neue E-Mail-Adresse',
        'email_pending_body' => 'Wir haben einen Link an :email geschickt. Ihre bisherige Adresse funktioniert weiter, bis Sie die neue bestätigen.',
        'email_pending' => 'Sehen Sie in :email nach dem Link zur Bestätigung.',
        'email_resend' => 'Link erneut senden',
        'email_resent' => 'Wir haben den Bestätigungslink erneut geschickt.',
        'email_cancel' => 'Änderung abbrechen',
        'email_cancelled' => 'Adressänderung abgebrochen.',
        'email_changed' => 'E-Mail-Adresse aktualisiert.',
        'email_link_dead' => 'Dieser Bestätigungslink ist abgelaufen oder wurde bereits verwendet. Fordern Sie unter „Mein Profil“ einen neuen an.',
        'email_taken' => 'Diese E-Mail-Adresse wird bereits verwendet.',
        'email_unchanged' => 'Das ist bereits Ihre E-Mail-Adresse.',

        'staff_card' => 'Angaben zur Beschäftigung',
        'staff_intro' => 'Von der Verwaltung in der Personalverwaltung gesetzt und hier zur Ansicht gezeigt.',
        'role' => 'Rolle',
        'no_role' => 'Keine Rolle zugewiesen',
        'locations' => 'Zugewiesener Standort',
        'all_locations' => 'Alle Standorte',
        'no_location' => 'Kein Standort zugewiesen',
        'status' => 'Kontostatus',
        'status_active' => 'Aktiv',
        'status_inactive' => 'Inaktiv',
        'status_archived' => 'Archiviert',
        'member_since' => 'Dabei seit',
    ],

    'preferences' => [
        'title' => 'Meine Präferenzen',
        'intro' => 'Wie sich die App für Sie liest. Jede folgt der Voreinstellung des Betriebs, bis Sie sie hier ändern.',

        'language_card' => 'Sprache',
        'language' => 'Primäre Sprache',
        'language_hint' => 'Ändert die Oberfläche nur für Sie. Was Ihr Betrieb selbst geschrieben hat — Leistungsnamen, Kundennotizen — wird nie übersetzt.',
        'language_default' => 'Sprache des Betriebs verwenden',

        'format_card' => 'Datum und Uhrzeit',
        'date_format' => 'Datumsformat',
        'time_format' => 'Zeitformat',
        'timezone' => 'Zeitzone',
        'timezone_hint' => 'Leer lassen, um der Zeitzone des Betriebs zu folgen.',
        'first_day_of_week' => 'Wochenbeginn',
        'use_business' => 'Einstellung des Betriebs verwenden',

        'calendar_card' => 'Kalender',
        'calendar_intro' => 'Wie sich Kalenderansichten für Sie öffnen.',
        'calendar_view' => 'Standardansicht',
        'calendar_views' => [
            'day' => 'Tag',
            'week' => 'Woche',
            'month' => 'Monat',
        ],
        'show_weekends' => 'Wochenenden anzeigen',
        'show_cancelled' => 'Abgesagte Termine anzeigen',
        'show_resource_color' => 'Farbe der Ressource anzeigen',
        'show_staff_color' => 'Farbe der Fachkraft anzeigen',

        'save' => 'Präferenzen speichern',
        'saved' => 'Präferenzen aktualisiert.',
        'reset_action' => 'Auf Standard zurücksetzen',
        'reset_hint' => 'Löscht Ihre persönlichen Entscheidungen, damit wieder jede Einstellung dem Betrieb folgt.',
        'reset' => 'Präferenzen auf die Voreinstellungen des Betriebs zurückgesetzt.',
    ],

    'password' => [
        'title' => 'Passwort ändern',
        'intro' => 'Wählen Sie eines, das Sie nirgendwo sonst verwenden.',
        'card' => 'Ihr Passwort',
        'hidden' => 'Ihr Passwort ist verborgen',
        'current' => 'Aktuelles Passwort',
        'new' => 'Neues Passwort',
        'confirm' => 'Neues Passwort bestätigen',
        'requirements' => 'Ihr Passwort braucht',
        'save' => 'Passwort ändern',
        'saved' => 'Passwort geändert.',
        'current_wrong' => 'Das ist nicht Ihr aktuelles Passwort.',
        'same_as_current' => 'Wählen Sie ein anderes Passwort als das aktuelle.',
        'mismatch' => 'Die Passwörter stimmen nicht überein.',
        'logout_others' => 'Von allen anderen Geräten abmelden',
        'logout_others_hint' => 'Dieser Browser bleibt angemeldet. Überall sonst wird das neue Passwort verlangt.',
    ],

    'notifications' => [
        'title' => 'Benachrichtigungen',
        'intro' => 'Welche Nachrichten Sie erreichen und auf welchem Weg. Sicherheitshinweise werden immer gesendet.',
        'saved' => 'Benachrichtigungseinstellungen aktualisiert.',
        'reset' => 'Benachrichtigungseinstellungen auf die Voreinstellungen zurückgesetzt.',
        'save' => 'Benachrichtigungseinstellungen speichern',
        'enable_all' => 'Alle einschalten',
        'disable_all' => 'Optionale Benachrichtigungen ausschalten',
        'bulk_hint' => 'Sicherheitshinweise bleiben so oder so an.',
        'always_on' => 'Immer an',
        'coming_soon' => 'Demnächst',
        'not_supported' => 'Für diese Benachrichtigung nicht verfügbar',

        'channels' => [
            'in_app' => 'In der App',
            'email' => 'E-Mail',
            'sms' => 'SMS',
            'push' => 'Push',
        ],

        'groups' => [
            'appointments' => [
                'label' => 'Termine',
                'description' => 'Was mit den Buchungen in Ihrem Kalender geschieht.',
            ],
            'clients' => [
                'label' => 'Kundschaft',
                'description' => 'Änderungen bei den Menschen, die Sie betreuen.',
            ],
            'team' => [
                'label' => 'Team',
                'description' => 'Ihr Dienstplan, Ihre Rolle und was Kolleginnen und Kollegen Ihnen schicken.',
            ],
            'resources' => [
                'label' => 'Ressourcen',
                'description' => 'Die Räume, Stühle und Geräte, von denen Ihre Arbeit abhängt.',
            ],
            'system' => [
                'label' => 'Sicherheit und Konto',
                'description' => 'Wie Sie erfahren, dass mit Ihrem Konto etwas geschehen ist. Diese werden immer gesendet.',
            ],
        ],

        'types' => [
            'booking.created' => 'Neuer Termin angelegt',
            'booking.assigned' => 'Termin mir zugewiesen',
            'booking.updated' => 'Termin geändert',
            'booking.rescheduled' => 'Termin verschoben',
            'booking.cancelled' => 'Termin abgesagt',
            'booking.completed' => 'Termin abgeschlossen',
            'booking.no_show' => 'Termin als nicht erschienen markiert',
            'booking.reminder' => 'Terminerinnerung',

            'client.assigned' => 'Neue Kundschaft mir zugewiesen',
            'client.updated' => 'Kundenprofil aktualisiert',
            'client.note_added' => 'Kundennotiz hinzugefügt',
            'client.file_uploaded' => 'Kundendatei hochgeladen',
            'client.mentioned' => 'Eine Kundennotiz erwähnt mich',

            'team.invitation' => 'Einladung ins Team',
            'team.location_assigned' => 'Person einem Standort zugewiesen',
            'team.hours_changed' => 'Arbeitszeiten geändert',
            'team.schedule_changed' => 'Dienstplan geändert',
            'team.mentioned' => 'Jemand erwähnt mich',
            'team.role_changed' => 'Rolle oder Rechte geändert',

            'resource.assigned' => 'Ressource mir zugewiesen',
            'resource.changed' => 'Ressource geändert',
            'resource.unavailable' => 'Ressource wird nicht verfügbar',
            'resource.conflict' => 'Konflikt bei einer Ressource',

            'security.alert' => 'Sicherheitshinweise',
            'security.password_changed' => 'Passwort geändert',
            'security.email_changed' => 'E-Mail-Adresse geändert',
            'security.new_login' => 'Neue Anmeldung erkannt',
            'security.account_alert' => 'Kontohinweise',
        ],
        /*
        | Je eine Zeile, die sagt, WANN die Nachricht kommt, statt den Titel zu
        | wiederholen. Wer entscheidet, ob er gestört werden will, braucht den
        | Auslöser und kein Synonym für die Überschrift.
        */
        'types_hint' => [
            'booking.created' => 'Jemand bucht einen Termin — online, am Tresen oder telefonisch.',
            'booking.assigned' => 'Ein Termin läuft auf Ihren Namen oder wird von einer Kollegin an Sie übergeben.',
            'booking.updated' => 'Leistungen, Preis oder Notizen eines Ihrer Termine ändern sich.',
            'booking.rescheduled' => 'Einer Ihrer Termine wandert auf einen anderen Tag oder eine andere Zeit.',
            'booking.cancelled' => 'Kundschaft oder eine Kollegin sagt einen Ihrer Termine ab.',
            'booking.completed' => 'Einer Ihrer Termine wird abgerechnet und als erledigt markiert.',
            'booking.no_show' => 'Jemand wird als nicht erschienen erfasst.',
            'booking.reminder' => 'Kurz bevor einer Ihrer Termine beginnt.',

            'client.assigned' => 'Jemand wird Ihrer Betreuung zugeordnet.',
            'client.updated' => 'Jemand bearbeitet die Angaben einer Ihnen zugewiesenen Person.',
            'client.note_added' => 'Zu einer Ihrer Kundinnen oder Kunden wird eine Notiz hinzugefügt.',
            'client.file_uploaded' => 'Zu einer Ihrer Kundinnen oder Kunden kommt ein Foto oder Dokument hinzu.',
            'client.mentioned' => 'Eine Kollegin schreibt Ihren Namen in eine Kundennotiz.',

            'team.invitation' => 'Jemand wird in den Betrieb eingeladen oder nimmt eine Einladung an.',
            'team.location_assigned' => 'Sie wechseln den Standort, oder jemand aus dem Team tut es.',
            'team.hours_changed' => 'Ihre regulären Arbeitszeiten werden geändert.',
            'team.schedule_changed' => 'Ein Dienstplan mit Ihren Schichten wird veröffentlicht oder geändert.',
            'team.mentioned' => 'Jemand schreibt Ihren Namen irgendwo in StyleDesk.',
            'team.role_changed' => 'Ihre Rolle ändert sich, oder das, was Sie tun dürfen.',

            'resource.assigned' => 'Ein Raum, Stuhl oder Gerät läuft auf Ihren Namen.',
            'resource.changed' => 'Eine Ressource, die Sie nutzen, wird umbenannt, verschoben oder ihre Zeiten geändert.',
            'resource.unavailable' => 'Eine Ressource, für die Sie eingeplant sind, wird für Wartung oder Reparatur geschlossen.',
            'resource.conflict' => 'Zwei Termine brauchen dieselbe Ressource zur selben Zeit.',

            'security.alert' => 'Mit Ihrem Konto geschieht etwas, von dem Sie unserer Meinung nach wissen sollten.',
            'security.password_changed' => 'Ihr Passwort wird geändert, von Ihnen oder von jemand anderem.',
            'security.email_changed' => 'Die E-Mail-Adresse, mit der Sie sich anmelden, wird geändert.',
            'security.new_login' => 'Ihr Konto wird von einem Gerät oder Ort genutzt, den wir noch nicht kennen.',
            'security.account_alert' => 'Ihr Konto wird gesperrt, ausgesetzt oder anderweitig eingeschränkt.',
        ],
    ],
];
