<?php

declare(strict_types=1);

/*
| Die Dienstplantafel des Teams: eine Zeile je Person, eine Spalte je Monat.
|
| Die Sprache hält drei Zustände auseinander, denn die ganze Ansicht existiert,
| um sie auf einen Blick zu unterscheiden: Veröffentlicht ist ein Monat, zu dem
| die Person eine E-Mail bekommen hat, Entwurf ein geplanter, aber nicht
| verschickter, und Nicht geplant ein Monat, an den noch niemand gedacht hat.
*/

return [

    'title' => 'Dienstplan',
    'intro' => 'Welche Monate abgedeckt sind, welche noch Entwürfe sind, und wer nichts geplant hat.',
    'summary' => '{0} Keine Mitarbeitenden|{1} :count Person|[2,*] :count Personen',

    'assign' => 'Dienstplan zuweisen',
    'add_shift' => 'Schicht hinzufügen',

    'search_label' => 'Personal durchsuchen',
    'search_placeholder' => 'Nach Name, E-Mail oder Position suchen',

    'filters' => [
        'month' => 'Monat',
        'year' => 'Jahr',
        'status' => 'Status des Dienstplans',
        'coverage' => 'Abdeckung',
        'all_statuses' => 'Alle Status',
        'all_coverage' => 'Alle Monate',
        'apply' => 'Anwenden',
        'reset' => 'Zurücksetzen',
    ],

    'statuses' => [
        'scheduled' => 'Geplant',
        'not-scheduled' => 'Nicht geplant',
        'draft' => 'Entwurf',
        'published' => 'Veröffentlicht',
    ],

    'coverage' => [
        'completed' => 'Vergangene Monate',
        'current' => 'Aktueller Monat',
        'future' => 'Kommende Monate',
    ],

    /*
    | Was eine Zelle sagt. „Änderungen“ ist der vierte Zustand, den die Ansicht
    | je Person ohnehin führt: einmal verschickt und seither bearbeitet — für
    | die Person ein Entwurf, für ihr Postfach keiner.
    */
    'states' => [
        'published' => 'Veröffentlicht',
        'draft' => 'Entwurf',
        'changes' => 'Änderungen offen',
        'not-scheduled' => 'Nicht geplant',
    ],

    'columns' => [
        'staff' => 'Person',
    ],

    'summary_line' => 'Monat :month · Jahr :year',
    'shifts_count' => '{1} :count Schicht|[2,*] :count Schichten',
    'scroll_hint' => 'Nach links und rechts scrollen, um jeden Monat zu sehen.',
    'this_month' => 'Dieser Monat',
    'cell_hint' => ':name · :month',
    'open_schedule' => 'Dienstplan von :name für :month öffnen',
    'start_schedule' => ':name einen Dienstplan für :month zuweisen',

    'menu' => [
        'view' => 'Dienstplan für :month ansehen',
        'assign' => 'Dienstplan für :month zuweisen',
    ],

    'actions_for' => 'Aktionen für :name',
    'showing' => ':from–:to von :total Mitarbeitenden werden angezeigt',
    'results' => [
        'zero' => 'Keine Mitarbeitenden',
        'one' => '1 Person',
        'many' => ':count Personen',
        'clear' => 'Filter zurücksetzen',
    ],
    'empty' => 'Niemand passt zu diesen Filtern.',
    'empty_hint' => 'Setzen Sie die Filter zurück, um das ganze Team zu sehen.',
    'no_months' => 'Kein Monat passt zu diesem Abdeckungsfilter.',

    'start' => [
        'title' => 'Dienstplan zuweisen',
        'intro' => 'Wählen Sie, für wen der Dienstplan ist und welchen Monat er abdeckt. Ein Monat wird als Ganzes geplant.',
        'staff' => 'Person',
        'choose_staff' => 'Person suchen oder auswählen',
        'continue' => 'Weiter zum Dienstplan',
    ],

    'month' => [
        'date' => 'Datum',
        'day' => 'Tag',
        'shift' => 'Schicht',
        'start' => 'Beginn',
        'end' => 'Ende',
        'break' => 'Pause',
        'hours' => 'Stunden gesamt',
        'status' => 'Status',
        'split' => 'Schicht :index',
        'minutes' => ':count Min.',
        'edit' => 'Dienstplan bearbeiten / neu zuweisen',
        'loading' => 'Wird geladen…',
        'failed' => 'Dieser Dienstplan konnte nicht geladen werden.',
    ],

    'legend' => [
        'title' => 'Legende',
        'published' => 'Alle haben diesen Monat per E-Mail erhalten.',
        'draft' => 'Geplant, aber noch niemandem mitgeteilt.',
        'not_scheduled' => 'Nichts geplant — braucht Aufmerksamkeit.',
    ],
];
