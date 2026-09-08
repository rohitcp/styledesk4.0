<?php

declare(strict_types=1);

/*
| Das Personalmodul: Verzeichnis, Profil und das Formular zum Anlegen und
| Bearbeiten.
|
| Die Feldbezeichnungen sind bewusst für alle drei dieselben. Wenn „Rufname“
| auf einem Profil das eine und im Formular das andere benennt, ist das genau
| das Auseinanderdriften, dem ein gemeinsamer Schlüssel vorbeugt.
*/

return [
    'title' => 'Mitarbeitende',
    'summary' => '{1} :count aktive Person|[2,*] :count aktive Personen',
    'summary_pending' => '{1} :count offene Einladung|[2,*] :count offene Einladungen',

    'add' => 'Mitarbeitende anlegen',
    'add_title' => 'Mitarbeitende anlegen',
    'add_intro' => 'Angaben zur Person und ihre Rolle. Arbeitszeiten, Verfügbarkeit und Einstellungen je Leistung werden eingerichtet, sobald der Datensatz besteht.',
    'adding' => 'Wird angelegt…',
    'save_and_add_another' => 'Speichern und weitere anlegen',
    'edit' => 'Bearbeiten',
    'edit_person' => ':name bearbeiten',
    'edit_title' => 'Mitarbeitende bearbeiten',
    'view_profile' => 'Profil ansehen',

    'created' => ':name gehört jetzt zu Ihrem Team.',
    'created_invited_to' => ':name wurde angelegt, eine Einladung ist an :email unterwegs.',
    'saved_person' => 'Die Angaben von :name wurden aktualisiert.',
    'add_failed' => 'Diese Person konnte gerade nicht angelegt werden. Bitte erneut versuchen.',
    'created_invited' => ':name wurde angelegt und die Einladung ist unterwegs.',
    'saved' => 'Mitarbeitende aktualisiert.',
    'deleted' => ':name wurde aus Ihrem Team entfernt.',
    'save_failed' => 'Ihre Änderungen konnten nicht gespeichert werden. Bitte prüfen Sie die Angaben und versuchen Sie es erneut.',
    'correct_fields' => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',
    'delete_confirm' => ':name aus Ihrem Team entfernen? Der Datensatz wird gelöscht, und falls die Person einen Zugang hatte, verliert sie den Zugriff auf diesen Betrieb.',

    /* Die Listenansicht — dieselbe Form, die auch die Kunden- und
       Leistungslisten verwenden. */
    'view' => 'Personal ansehen',
    'manage_schedule' => 'Dienstplan verwalten',
    'set_time_off' => 'Abwesenheit eintragen',
    'activate' => 'Aktivieren',
    'deactivate' => 'Deaktivieren',
    'deactivate_confirm' => ':name deaktivieren? Datensatz, Leistungen und Räume bleiben erhalten, die Person wird aber nicht mehr zur Buchung angeboten.',
    'activated_person' => ':name ist wieder aktiv.',
    'deactivated_person' => ':name ist nicht mehr aktiv.',
    'add_schedule' => 'Dienstplan anlegen',
    'results' => [
        'zero' => 'Keine Mitarbeitenden gefunden',
        'one' => '1 Person gefunden',
        'many' => ':count Personen gefunden',
        'empty' => 'Niemand passt zu Ihrer Suche oder Ihren Filtern.',
        'clear' => 'Filter zurücksetzen',
    ],
    'showing' => ':from–:to von :total Mitarbeitenden werden angezeigt',
    'none_yet' => 'Noch keine Mitarbeitenden',
    'none_yet_hint' => 'Legen Sie Mitarbeitende an, um Dienstpläne, Leistungen, Standorte und Terminverfügbarkeit zu verwalten.',

    'search_placeholder' => 'Nach Name, E-Mail, Telefon oder Position suchen',
    'search_label' => 'Personal durchsuchen',
    'apply_filters' => 'Filter anwenden',

    'filters' => [
        'role' => 'Rolle',
        'all_roles' => 'Alle Rollen',
        'location' => 'Standort',
        'all_locations' => 'Alle Standorte',
        'service' => 'Leistung',
        'all_services' => 'Alle Leistungen',
        'provider_type' => 'Art der Tätigkeit',
        'all_provider_types' => 'Alle Tätigkeitsarten',
        'employment' => 'Anstellung',
        'all_employment' => 'Alle Anstellungsarten',
        'status' => 'Status',
        'all_statuses' => 'Alle Status',
        'sort' => 'Sortieren nach',
    ],

    'columns' => [
        'name' => 'Name',
        'role' => 'Rolle',
        'location' => 'Standort',
        'contact' => 'Kontakt',
        'services' => 'Leistungen',
        'status' => 'Status',
        'last_login' => 'Letzte Anmeldung',
        'actions' => 'Aktionen',
    ],

    'empty_title' => 'Niemand passt zu diesen Filtern.',
    'empty_hint' => 'Setzen Sie die Filter zurück oder laden Sie jemanden im Team-Schritt ein.',
    'never' => 'Nie',
    'all_locations' => 'Alle Standorte',
    'actions_for' => 'Aktionen für :name',

    'cards' => [
        'shift_rule' => 'Schichtregel',
        'shift_rule_hint' => 'Weisen Sie dieser Person eine wiederverwendbare Schichtregel zu. Sie wird beim Erzeugen ihres Dienstplans verwendet.',
        'basic' => 'Basisangaben',
        'contact' => 'Kontaktdaten',
        'employment' => 'Rolle und Anstellung',
        'employment_hint' => 'Die Rolle bestimmt, was jemand in StyleDesk tun darf. Die Anstellungsart ist, wie der Betrieb die Person beschäftigt — beides ist voneinander unabhängig.',
        'account' => 'Konto',
    ],

    'fields' => [
        'shift_rule' => 'Schichtregel',
        'no_shift_rule' => 'Keine Schichtregel zugewiesen',
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'middle_name' => 'Zweiter Vorname',
        'preferred_name' => 'Rufname',
        'preferred_name_placeholder' => 'Wie Team und Kundschaft die Person nennen',
        'pronouns' => 'Pronomen',
        'job_title' => 'Position',
        'job_title_placeholder' => 'Erste Kraft',
        'employee_ref' => 'Personalnummer',
        'employee_ref_hint' => 'Wird beim Speichern automatisch vergeben.',
        'avatar' => 'Profilbild',
        'avatar_hint' => 'JPG, PNG oder WEBP, bis 2 MB. Wird auf der Buchungsseite und in der Teamliste gezeigt.',
        'bio' => 'Kurzprofil',
        'bio_placeholder' => 'Wird auf der Buchungsseite gezeigt, wenn die Person Termine annimmt.',

        'email' => 'Haupt-E-Mail',
        'email_hint' => 'An diese Adresse geht auch jede Einladung.',
        'work_email' => 'Geschäftliche E-Mail',
        'phone' => 'Haupttelefon',
        'phone_type' => 'Art des Anschlusses',
        'secondary_phone' => 'Zweite Telefonnummer',
        'emergency_contact_name' => 'Notfallkontakt',
        'emergency_contact_phone' => 'Notfallnummer',
        'emergency_contact_relationship' => 'Beziehung',
        'relationship_placeholder' => 'Partner:in',
        'address' => 'Adresse',

        'role' => 'Rolle',
        'role_placeholder' => 'Rolle wählen',
        'role_hint' => 'Sie können nur Rollen innerhalb Ihres eigenen Zugriffs vergeben.',
        'location' => 'Hauptstandort',
        'employment_type' => 'Anstellungsart',
        'provider_type' => 'Art der Tätigkeit',
        'specialities' => 'Schwerpunkte',
        'services' => 'Leistungen, die sie erbringt',
        'services_hint' => 'Wem Leistungen zugewiesen sind, der wird namentlich buchbar.',
        'services_placeholder' => 'Leistungen suchen oder auswählen',
        'resources' => 'Ressourcen, an denen sie arbeitet',
        'resources_hint' => 'Die Stühle, Räume oder Plätze, die diese Person nutzt. Leer lassen, wenn jeder passt.',
        'resources_placeholder' => 'Ressourcen suchen oder auswählen',
        'date_of_birth' => 'Geburtsdatum',
        'started_on' => 'Eintrittsdatum',

        'account_status' => 'Kontostatus',
        'account_status_hint' => 'Eine inaktive Person kann nicht gebucht werden und erhält keine neuen Termine.',
        'login_enabled' => 'Anmeldung erlauben',
        'login_enabled_hint' => 'Die Person erhält ein eigenes StyleDesk-Konto. Aus lassen, wenn sie nur im Kalender erscheinen soll.',
        'send_invitation' => 'Einladung jetzt senden',
        'send_invitation_hint' => 'Schickt per E-Mail einen Link zum Festlegen eines Passworts und zum Beitreten. Sie können sie auch später senden.',
        'invitation_message' => 'Nachricht',
        'invitation_message_placeholder' => 'Wir freuen uns auf die Zusammenarbeit.',
    ],

    'not_specified' => 'Keine Angabe',

    'validation' => [
        'shift_rule_unavailable' => 'Diese Schichtregel steht für den gewählten Standort nicht zur Verfügung. Bitte eine andere wählen.',
        'first_name_required' => 'Der Vorname ist erforderlich.',
        'last_name_required' => 'Der Nachname ist erforderlich.',
        'email_required' => 'Die Haupt-E-Mail ist erforderlich.',
        'email_invalid' => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'email_taken' => 'Jemand in Ihrem Team verwendet diese E-Mail-Adresse bereits.',
        'role_required' => 'Wählen Sie eine Rolle für diese Person.',
        'role_invalid' => 'Sie können nur Rollen innerhalb Ihres eigenen Zugriffs vergeben.',
        'location_invalid' => 'Wählen Sie einen Ihrer eigenen Standorte.',
        'service_invalid' => 'Wählen Sie eine Ihrer eigenen Leistungen.',
        'role_not_yours' => 'Diese Rolle können Sie nicht vergeben.',
        'own_role' => 'Ihre eigene Rolle können Sie nicht ändern. Bitten Sie eine andere Person mit Administrationsrechten darum.',
        'avatar_max' => 'Das Profilbild darf höchstens 2 MB groß sein.',
        'avatar_mimes' => 'Verwenden Sie ein JPG-, PNG- oder WEBP-Bild.',
    ],

    /*
     * Die Bezeichnungen der Angaben auf der Profilseite.
     *
     * Getrennt von `fields`, weil das Profil benennt, was ein Wert ist
     * („Vollständiger Name“), während das Formular nach seinen Teilen fragt
     * („Vorname“). Beides zusammenzulegen hieße, dass ein Bildschirm die Frage
     * stellt, die der andere gerade beantwortet.
     */
    'profile' => [
        'about' => 'Überblick',
        'legal_name' => 'Vollständiger Name',
        'preferred_name' => 'Rufname',
        'pronouns' => 'Pronomen',
        'employee_ref' => 'Personalnummer',

        'contact' => 'Kontakt',
        'email' => 'Haupt-E-Mail',
        'work_email' => 'Geschäftliche E-Mail',
        'phone' => 'Haupttelefon',
        'secondary_phone' => 'Zweite Telefonnummer',
        'address' => 'Adresse',
        'emergency_contact' => 'Notfallkontakt',

        'access' => 'Rolle und Zugriff',
        'role' => 'Rolle',
        'location' => 'Hauptstandort',
        'login' => 'Anmeldung',
        'account' => 'Konto',
        'last_login' => 'Letzte Anmeldung',

        'services' => 'Leistungen',
        'assign_services' => 'Leistungen zuweisen',

        'employment' => 'Anstellung',
        'employment_type' => 'Anstellungsart',
        'provider_type' => 'Art der Tätigkeit',
        'specialities' => 'Schwerpunkte',
        'added' => 'Angelegt am',

        'invitation' => 'Einladung',
        'sent_to' => 'Gesendet an',

        'activity' => 'Aktivität',
        'activity_hint' => 'Verwaltungsänderungen an diesem Datensatz.',
        'activity_empty' => 'Noch nichts erfasst.',

        'bookable' => 'Buchbar',
        'no_login' => 'Kein Zugang',
        'summary_email' => 'E-Mail',
        'summary_phone' => 'Telefon',
        'summary_location' => 'Standort',
    ],

    'edit_staff' => 'Mitarbeitende bearbeiten',
    'more_actions' => 'Mehr',
    'set_on_leave' => 'Auf abwesend setzen',
    'on_leave_person' => ':name ist abwesend.',
    'services_added' => '{1} :count Leistung hinzugefügt.|[2,*] :count Leistungen hinzugefügt.',
    'service_removed' => ':name entfernt. Die Leistung selbst bleibt unberührt.',
    'shift_rule_assigned' => 'Auf :name angewendet.',
    'shift_rule_cleared' => 'Schichtregel entfernt.',

    'tabs' => [
        'overview' => 'Überblick',
        'schedule' => 'Dienstplan',
        'services' => 'Leistungen',
        'notes' => 'Notizen',
    ],

    /* Die Auswertung im Reiter Überblick. Nur, was die Daten heute hergeben —
       die Terminzahlen brauchen ein Buchungsmodul, und eine Kachel, die „—“
       zeigt, bringt Lesenden bei, die Zeile zu übergehen. */
    'report' => [
        'shifts_this_week' => 'Schichten diese Woche',
        'hours_this_week' => 'Stunden diese Woche',
        'shifts_this_month' => 'Schichten diesen Monat',
        'hours_this_month' => 'Stunden diesen Monat',
        'upcoming_shifts' => 'Kommende Schichten',
        'services' => 'Leistungen',
    ],

    'schedule_tab' => [
        'shift_rule' => 'Schichtregel',
        'current_rule' => 'Aktuelle Schichtregel',
        'remove_rule' => 'Schichtregel entfernen',
        'remove_rule_confirm' => ':rule von :name entfernen? Bereits eingeplante Schichten bleiben, wie sie sind; entfernt wird nur das Muster dahinter.',
        'no_rule' => 'Keine Schichtregel zugewiesen.',
        'assign_rule' => 'Schichtregel zuweisen',
        'change_rule' => 'Schichtregel wechseln',
        'working_schedule' => 'Arbeitsplan',
        'weeks' => '{1} 1 Woche|[2,*] :count Wochen',
        'previous' => 'Zurück',
        'today' => 'Heute',
        'next' => 'Weiter',
        'not_working' => 'Arbeitet nicht',
    ],

    'services_tab' => [
        'title' => 'Leistungen, die sie ausführen darf',
        'intro' => 'Wofür diese Person gebucht werden darf. Eine zu entfernen nimmt ihr die Buchbarkeit dafür — die Leistung selbst bleibt unberührt.',
        'add' => 'Leistungen hinzufügen',
        'choose' => 'Hinzuzufügende Leistungen',
        'remove' => 'Entfernen',
        'remove_confirm' => ':service von :name entfernen? Die Person ist dafür dann nicht mehr buchbar. Die Leistung selbst bleibt unberührt.',
        'none' => 'Keine Leistungen zugewiesen',
        'none_hint' => ':name kann erst namentlich gebucht werden, wenn mindestens eine Leistung zugewiesen ist.',
    ],

    'notes' => [
        'title' => 'Interne Notizen',
        'intro' => 'Notizen, die der Betrieb zu dieser Person führt — Einsatzplanung, Schulung, Verfügbarkeit. Nie für Kundschaft sichtbar und nie auf den Buchungsseiten.',
        'body' => 'Notiz',
        'placeholder' => 'Alles, was das Team wissen sollte.',
        'add' => 'Notiz hinzufügen',
        'added' => 'Notiz hinzugefügt.',
        'deleted' => 'Notiz gelöscht.',
        'delete_confirm' => 'Diese Notiz löschen? Sie lässt sich nicht zurückholen.',
        'body_required' => 'Schreiben Sie etwas, bevor Sie die Notiz hinzufügen.',
        'none' => 'Noch keine Notizen.',
        'someone' => 'Jemand',
    ],

    /*
    | Auslastung — wie viel vom buchbaren Tag jeder Person gebucht ist.
    |
    | Die Sprache ist die einer Führungskraft, nicht die eines Berichts: „3 %
    | unter Ziel“ statt „Abweichung -3“ und „nicht eingeplant“ statt „0 %“. Wer
    | gar nicht im Dienstplan stand, hatte keinen Tag zu füllen.
    */
    'utilization' => [
        'title' => 'Auslastung',
        'subtitle' => 'Wie viel der buchbaren Zeit Ihres Teams gebucht ist',
        'intro' => 'Die Auslastung wird an den Stunden gemessen, für die jemand tatsächlich zur Kundenbetreuung eingeplant ist — Planzeit abzüglich Pausen und nicht buchbarer Zeit.',

        'period' => 'Zeitraum',
        'loading' => 'Wird aktualisiert…',
        'apply' => 'Anwenden',
        'cancel' => 'Abbrechen',
        'clear' => 'Zurücksetzen',
        'search' => 'Personal durchsuchen…',
        'all_locations' => 'Alle Standorte',
        'all_roles' => 'Alle Rollen',
        'all_statuses' => 'Alle Status',
        'filters_active' => 'Filter',

        'average' => 'Durchschnittliche Auslastung',
        'average_for' => 'Teamdurchschnitt',
        'target' => 'Ziel: :target %',
        'target_met' => 'Ziel erreicht',
        'above_target' => ':count % über Ziel',
        'below_target' => ':count % unter Ziel',
        'on_target' => 'Im Ziel',

        'booked_line' => ':hours Std. gebucht',
        'available_line' => 'von :hours Std. buchbar',
        'unused_line' => ':hours Std. ungenutzt',
        'scheduled_count' => '{1} 1 Person eingeplant|[2,*] :count Personen eingeplant',
        'bookings_count' => '{0} Keine Buchungen|{1} 1 Buchung|[2,*] :count Buchungen',
        'used_of_short' => ':used von :available Std. gebucht',

        'summary' => [
            'scheduled' => 'Eingeplantes Personal',
            'bookable' => 'Buchbare Stunden',
            'booked' => 'Gebuchte Stunden',
            'unused' => 'Ungenutzte Stunden',
            'on_target' => 'Im Ziel oder darüber',
            'under' => 'Unterausgelastet',
        ],

        'statuses' => [
            'high' => 'Hohe Auslastung',
            'on_target' => 'Ziel erreicht',
            'near_target' => 'Nahe am Ziel',
            'low' => 'Geringe Auslastung',
            'very_low' => 'Sehr gering',
            'unscheduled' => 'Nicht eingeplant',
        ],

        'timeline' => [
            'title' => 'Der Tag des Teams',
            'legend' => [
                'booked' => 'Gebucht',
                'available' => 'Frei',
                'break' => 'Pause',
                'blocked' => 'Nicht buchbar',
                'off' => 'Außerhalb der Arbeitszeit',
            ],
        ],

        'detail' => [
            'utilization' => 'Auslastung',
            'target' => 'Ziel',
            'bookable' => 'Buchbar',
            'booked' => 'Gebucht',
            'unused' => 'Ungenutzt',
            'bookings' => 'Buchungen',
            'revenue' => 'Umsatz',
            'average_value' => 'Durchschnittliche Buchung',
            'vs_team' => 'ggü. Teamdurchschnitt',
            'vs_target' => 'ggü. Ziel',
            'day' => 'Der Tag',
            'daily' => 'Tag für Tag',
            'services' => 'Was gemacht wurde',
            'gaps' => 'Freie Kapazität',
            'gaps_hint' => 'Lücken, die lang genug zum Verkaufen sind, und was hineinpasst.',
            'gaps_none' => 'An diesem Tag gibt es keine verkaufbaren Lücken.',
            'no_bookings' => 'In diesem Zeitraum keine Buchungen.',
            'minutes' => ':count Min.',
            'close' => 'Schließen',
            'open_staff' => 'Personaldatensatz öffnen',
        ],

        'comparison' => 'Teamvergleich',

        'table' => [
            'staff' => 'Person',
            'location' => 'Standort',
            'scheduled' => 'Eingeplant',
            'bookable' => 'Buchbar',
            'booked' => 'Gebucht',
            'idle' => 'Ungenutzt',
            'bookings' => 'Buchungen',
            'utilization' => 'Auslastung',
            'target' => 'Ziel',
            'variance' => 'Abweichung',
            'revenue' => 'Umsatz',
            'status' => 'Status',
        ],

        'empty' => 'Kein Dienstplan verfügbar',
        'empty_hint' => 'Die Auslastung lässt sich erst berechnen, wenn Arbeitszeiten veröffentlicht sind.',
        'empty_cta' => 'Dienstplan ansehen',
        'no_matches' => 'Niemand passt zu diesen Filtern.',
    ],
];
