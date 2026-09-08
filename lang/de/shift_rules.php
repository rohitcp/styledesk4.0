<?php

declare(strict_types=1);

/*
| Einstellungen → Personal → Schichtregeln.
|
| Eine Schichtregel ist ein wiederverwendbares Arbeitsmuster; ein Dienstplan ist
| das, was eine solche Regel auf eine Person anwendet. Der Text ist bewusst um
| diesen Unterschied herum formuliert, denn genau ihn verstehen Lesende falsch.
*/

return [

    'title' => 'Schichtregeln',
    'intro' => 'Wiederverwendbare Arbeitsmuster. Eine Regel ist eine Vorlage — sie jemandem zuzuweisen und einen Tag zu ändern, den die Person wirklich arbeitet, geschieht unter Personal → Dienstplan.',
    'add' => 'Schichtregel anlegen',
    'add_title' => 'Schichtregel anlegen',
    'edit_title' => 'Schichtregel bearbeiten',
    'saving' => 'Wird gespeichert…',
    'save' => 'Regel speichern',
    'save_and_add_another' => 'Speichern und weitere anlegen',

    'created' => ':name gespeichert.',
    'updated' => ':name aktualisiert.',
    'deleted' => 'Schichtregel gelöscht.',
    'duplicated' => ':name kopiert. Geben Sie der Kopie einen eigenen Namen und speichern Sie sie.',
    'copy_of' => ':name (Kopie)',
    'made_active' => ':name ist aktiv.',
    'made_inactive' => ':name wird für neue Dienstpläne nicht mehr angeboten.',

    'sections' => [
        'basics' => 'Basisangaben',
        'days' => 'Arbeitstage und -zeiten',
        'break' => 'Pause',
        'limits' => 'Stundenregeln',
        'flexibility' => 'Spielraum',
        'dates' => 'Geltungszeitraum',
        'split' => 'Einstellungen für geteilte Dienste',
    ],

    'fields' => [
        'name' => 'Name der Regel',
        'name_placeholder' => 'Standard Vollzeit',
        'description' => 'Beschreibung',
        'description_placeholder' => 'Wann oder warum diese Regel verwendet werden soll.',
        'location_scope' => 'Gilt für',
        'locations' => 'Standorte',
        'locations_placeholder' => 'Standorte suchen oder auswählen',
        'status' => 'Status',

        'break_type' => 'Pause',
        'break_minutes' => 'Länge der Pause',
        'break_starts_at' => 'Pause beginnt',
        'break_ends_at' => 'Pause endet',

        'max_hours_per_day' => 'Höchststunden pro Tag',
        'max_hours_per_week' => 'Höchststunden pro Woche',
        'min_hours_per_shift' => 'Mindeststunden je Schicht',
        'max_hours_per_shift' => 'Höchststunden je Schicht',
        'min_rest_hours' => 'Mindestruhe zwischen Schichten',
        'min_rest_hours_hint' => 'Verhindert einen Plan, der um 23 Uhr endet und um 6 Uhr wieder beginnt.',
        'max_consecutive_days' => 'Höchstzahl aufeinanderfolgender Arbeitstage',

        'allow_overtime' => 'Überstunden erlauben',
        'overtime_after_hours' => 'Überstunden ab',
        'max_overtime_hours' => 'Höchstens Überstunden',
        'allow_adjustment' => 'Anpassung des Dienstplans erlauben',
        'allow_adjustment_hint' => 'Die Leitung darf eine erzeugte Schicht verschieben, ohne diese Regel zu ändern.',
        'allow_split_shift' => 'Geteilte Dienste erlauben',
        'allow_split_shift_hint' => 'Erlaubt einer Person, am selben Tag mehrere getrennte Schichtzeiträume zu arbeiten.',
        'allow_same_employee_multiple_periods' => 'Mehrere Zeiträume für dieselbe Person erlauben',
        'allow_same_employee_multiple_periods_hint' => 'Dieselbe Person darf mehr als einen Zeitraum am Tag arbeiten, sofern sie sich nicht überschneiden.',
        'max_periods_per_employee_per_day' => 'Höchstzahl Zeiträume je Person und Tag',
        'min_gap_minutes' => 'Mindestabstand zwischen geteilten Diensten',
        'min_gap_hint' => 'Arbeitsfreie Zeit, die zwischen zwei Zeiträumen derselben Person liegen muss.',
        'minutes_unit' => 'Minuten',

        'effective_from' => 'Gültig ab',
        'effective_until' => 'Gültig bis',
        'effective_hint' => 'Beide leer lassen für eine Regel, die immer gilt.',

        'hours_unit' => 'Stunden',
        'hours_per_week' => 'Stunden / Woche',
        'days_unit' => 'Tage',
    ],

    'columns' => [
        'day' => 'Tag',
        'business_hours' => 'Öffnungszeiten',
        'name' => 'Regel',
        'location' => 'Standort',
        'days' => 'Arbeitstage',
        'hours' => 'Standardstunden',
        'weekly' => 'Wochenstunden',
        'overtime' => 'Überstunden',
        'status' => 'Status',
        'assigned' => 'Personal',
        'updated' => 'Zuletzt aktualisiert',
    ],

    'statuses' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'location_scopes' => [
        'all' => 'Alle Standorte',
        'specific' => 'Bestimmte Standorte',
    ],

    'break_types' => [
        'none' => 'Keine Pause',
        'fixed' => 'Feste Pause',
        'duration' => 'Nur Dauer',
    ],

    'break_custom' => 'Eigene',
    'minutes' => ':count Min.',
    'hours_vary' => 'Je nach Tag verschieden',
    'no_working_days' => 'Keine Arbeitstage',
    'all_locations' => 'Alle Standorte',
    'overtime_allowed' => 'Erlaubt',
    'overtime_not_allowed' => 'Nicht erlaubt',
    'not_set' => 'Nicht gesetzt',

    'assigned_staff' => 'Zugewiesenes Personal',
    'assigned_count' => '{0} Noch niemand auf dieser Regel|{1} :count Person|[2,*] :count Personen',
    'assigned_where' => 'Personen werden im Formular zum Anlegen oder Bearbeiten einer Person auf eine Regel gesetzt. Ihren tatsächlichen, datierten Plan erzeugen Sie unter Personal → Dienstplan.',

    'duplicate' => 'Duplizieren',
    'activate' => 'Aktivieren',
    'deactivate' => 'Deaktivieren',
    'deactivate_confirm' => ':name deaktivieren? Sie bleibt auf den Dienstplänen lesbar, die sie schon nutzen, wird aber für neue nicht mehr angeboten.',
    'delete_confirm' => ':name löschen? Diese Regel wird dauerhaft entfernt.',
    'delete_blocked' => ':name wird von :count Personen genutzt und kann deshalb nicht gelöscht werden. Deaktivieren Sie sie — die Pläne, die sie nutzen, brauchen sie lesbar.',

    'validation' => [
        'period_name_required' => 'Geben Sie jedem Schichtzeitraum einen Namen.',
        'period_times_required' => 'Geben Sie jedem Schichtzeitraum Beginn und Ende.',
        'period_ends_after_starts' => 'Ein Schichtzeitraum muss später enden, als er beginnt.',
        'period_outside_business_hours' => 'Der Schichtzeitraum muss innerhalb der Arbeitszeiten des Betriebs liegen.',
        'periods_required' => 'Fügen Sie mindestens einen Schichtzeitraum hinzu, oder schalten Sie geteilte Dienste ab.',
        'period_break_too_long' => 'Die Pause ist länger als der Zeitraum.',
        'name_required' => 'Geben Sie der Regel einen Namen.',
        'name_taken' => 'Eine Regel mit diesem Namen gibt es bereits.',
        'days_required' => 'Wählen Sie mindestens einen Arbeitstag.',
        'ends_after_starts' => 'Das Ende muss nach dem Beginn liegen.',
        'periods_overlap' => 'Die Arbeitszeiträume am :day überschneiden sich.',
        'split_not_allowed' => 'Diese Regel erlaubt keine geteilten Dienste, der :day darf also nur einen Zeitraum haben.',
        'break_too_long' => 'Die Pause ist länger als der kürzeste Arbeitstag.',
        'break_minutes_required' => 'Wählen Sie, wie lang die Pause ist.',
        'break_times_required' => 'Geben Sie der festen Pause Beginn und Ende.',
        'weekly_hours_positive' => 'Die Höchststunden pro Woche müssen größer als null sein.',
        'min_shift_over_max' => 'Die Mindeststunden je Schicht dürfen den Höchstwert nicht überschreiten.',
        'until_before_from' => 'Das Ende der Gültigkeit kann nicht vor ihrem Beginn liegen.',
        'locations_required' => 'Wählen Sie mindestens einen Standort, oder lassen Sie die Regel überall gelten.',
    ],

    'results' => [
        'zero' => 'Keine Schichtregeln gefunden',
        'one' => '1 Schichtregel gefunden',
        'many' => ':count Schichtregeln gefunden',
        'empty' => 'Keine Schichtregel passt zu Ihrer Suche oder Ihren Filtern.',
        'clear' => 'Filter zurücksetzen',
    ],
    'showing' => ':from–:to von :total Schichtregeln werden angezeigt',
    'none_yet' => 'Noch keine Schichtregeln',
    'none_yet_hint' => 'Eine Schichtregel ist ein Arbeitsmuster, das Sie einmal schreiben und auf beliebig viele Personen anwenden — „Standard Vollzeit“, „Wochenenddienst“, „Teilzeit vormittags“.',

    'weekdays_short' => [
        0 => 'So',
        1 => 'Mo',
        2 => 'Di',
        3 => 'Mi',
        4 => 'Do',
        5 => 'Fr',
        6 => 'Sa',
    ],

    'filters' => [
        'all_statuses' => 'Alle Status',
        'all_locations' => 'Alle Standorte',
    ],

    /* Der Wocheneditor ist dieselbe Komponente wie in den Standortzeiten, daher
       werden nur die abweichenden Worte überschrieben: Ein Muster hat
       Arbeitstage und freie Tage, wo eine Filiale geöffnet oder geschlossen ist. */
    'hours_editor' => [
        'open' => 'Arbeitet',
        'closed' => 'Frei',
        'closed_all_day' => 'Arbeitet nicht',
        'add_period' => 'Weitere Zeitspanne',
        'opening_time' => 'Beginn am :day',
        'closing_time' => 'Ende am :day',
    ],

    'hours_are_global' => 'Die Arbeitszeiten des Betriebs gelten für Planung und Buchung. Sie hier zu ändern ändert sie überall.',
    'edit_business_hours' => 'Arbeitszeiten des Betriebs bearbeiten',
    'no_location_yet' => 'Legen Sie einen Standort an, bevor Sie eine Regel schreiben — eine Arbeitswoche gehört zu einer Filiale.',
    'closed' => 'Geschlossen',

    'sections_split' => 'Einstellungen für geteilte Dienste',
    'split_intro' => 'Teilen Sie den Betriebstag in benannte Zeiträume, um die Besetzung zu planen. Jeder Zeitraum muss in die Arbeitszeiten fallen.',

    'periods' => [
        'name' => 'Name der Schicht',
        'name_placeholder' => 'Frühschicht',
        'starts_at' => 'Beginn',
        'ends_at' => 'Ende',
        'break' => 'Pause',
        'no_break' => 'Keine Pause',
        'status' => 'Status',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'add' => '+ Schichtzeitraum hinzufügen',
        'remove' => 'Entfernen',
        'minutes' => ':count Min.',
        'empty' => 'Noch keine Schichtzeiträume. Legen Sie den ersten an, um den Betriebstag zu teilen.',
    ],

    'enable' => 'Schichtregeln aktivieren',
    'enable_hint' => 'Aktiviert wiederverwendbare Regeln für die Personalplanung in diesem Betrieb.',
    'feature_on' => 'Schichtregeln sind an.',
    'feature_off' => 'Schichtregeln sind aus. Es wurde nichts gelöscht.',
    'feature_off_title' => 'Schichtregeln sind abgeschaltet',
    'feature_off_kept' => '{0} Schalten Sie sie ein, um ein Arbeitsmuster zu schreiben, das Sie auf beliebig viele Personen anwenden können.|{1} Ihre :count Regel ist noch da — schalten Sie Schichtregeln ein, um sie zu sehen.|[2,*] Ihre :count Regeln sind noch da — schalten Sie Schichtregeln ein, um sie zu sehen.',
    'rule_count' => '{0} Schichtregeln|{1} :count Schichtregel|[2,*] :count Schichtregeln',
    'back_to_list' => 'Zurück zu den Regeln',
    'delete_title' => 'Schichtregel löschen?',
];
