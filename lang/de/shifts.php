<?php

declare(strict_types=1);

/*
| Die Schichtansicht: Arbeitszeiten an einem konkreten Datum.
|
| Bewusst im Gegensatz zum Dienstplan daneben formuliert — ein Dienstplan ist
| das wiederkehrende Muster, eine Schicht ein datierter Block — weil sich beides
| leicht verwechseln lässt und der Text die Stelle ist, an der dieser
| Unterschied tatsächlich gemacht wird.
*/

return [

    'title' => 'Schichten',
    'intro' => 'Arbeitszeiten an einem konkreten Datum. Eine Schicht überschreibt für diesen Tag den wiederkehrenden Dienstplan.',
    'add' => 'Schicht hinzufügen',
    'add_title' => 'Schicht hinzufügen',
    'edit_title' => 'Schicht bearbeiten',
    'adding' => 'Wird hinzugefügt…',
    'saving' => 'Wird gespeichert…',
    'save' => 'Schicht speichern',
    'save_and_add_another' => 'Speichern und weitere hinzufügen',

    'created' => 'Schicht für :name hinzugefügt.',
    'updated' => 'Schicht aktualisiert.',
    'deleted' => 'Schicht entfernt.',
    'delete_confirm' => 'Diese Schicht von :name am :date entfernen?',
    'correct_fields' => 'Prüfen Sie die markierten Felder und versuchen Sie es erneut.',

    'fields' => [
        'staff' => 'Person',
        'staff_placeholder' => 'Person suchen oder auswählen',
        'location' => 'Standort',
        'location_placeholder' => 'Standort suchen oder auswählen',
        'date' => 'Datum',
        'starts_at' => 'Beginn',
        'ends_at' => 'Ende',
        'break' => 'Pause',
        'break_hint' => 'Unbezahlte Zeit innerhalb der Schicht.',
        'type' => 'Art der Schicht',
        'status' => 'Status',
        'notes' => 'Notizen',
        'notes_placeholder' => 'Was jede Person wissen sollte, die den Dienstplan liest.',
    ],

    'columns' => [
        'staff' => 'Person',
        'date' => 'Datum',
        'hours' => 'Stunden',
        'break' => 'Pause',
        'location' => 'Standort',
        'type' => 'Art',
        'status' => 'Status',
    ],

    'filters' => [
        'all_staff' => 'Ganzes Team',
        'all_locations' => 'Alle Standorte',
        'all_types' => 'Alle Arten',
        'all_statuses' => 'Alle Status',
        'from' => 'Von',
        'until' => 'Bis',
    ],

    'types' => [
        'regular' => 'Regulär',
        'overtime' => 'Überstunden',
        'cover' => 'Vertretung',
        'training' => 'Schulung',
        'on-call' => 'Rufbereitschaft',
        'custom' => 'Eigene',
    ],

    'statuses' => [
        'scheduled' => 'Geplant',
        'confirmed' => 'Bestätigt',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgesagt',
    ],

    'minutes' => ':count Min.',
    'no_break' => 'Keine',

    'view' => 'Schicht ansehen',
    'duplicate' => 'Duplizieren',
    'cancel_shift' => 'Schicht absagen',
    'cancel_confirm' => 'Diese Schicht absagen? Sie bleibt als abgesagt im Dienstplan stehen, damit alle sehen, dass sie entfallen ist.',
    'cancelled_toast' => 'Schicht abgesagt.',

    'validation' => [
        'business_closed' => 'Der Betrieb hat am :day geschlossen, es kann also niemand eingeteilt werden. Wählen Sie einen anderen Tag, oder öffnen Sie ihn unter Einstellungen → Betrieb → Arbeitszeiten.',
        'outside_business_hours' => 'Diese Zeiten liegen außerhalb der Öffnungszeiten (:hours).',
        'ends_after_start' => 'Das Ende muss nach dem Beginn liegen.',
        'break_too_long' => 'Die Pause ist länger als die Schicht.',
        'clash' => ':name hat an dem Tag bereits eine Schicht, die sich mit diesen Zeiten überschneidet.',
        'staff_required' => 'Wählen Sie, wer diese Schicht übernimmt.',
        'date_required' => 'Wählen Sie den Tag dieser Schicht.',
    ],

    'results' => [
        'zero' => 'Keine Schichten gefunden',
        'one' => '1 Schicht gefunden',
        'many' => ':count Schichten gefunden',
        'empty' => 'Keine Schicht passt zu Ihrer Suche oder Ihren Filtern.',
        'clear' => 'Filter zurücksetzen',
    ],
    'showing' => ':from–:to von :total Schichten werden angezeigt',
    'none_yet' => 'Noch keine Schichten',
    'none_yet_hint' => 'Legen Sie eine Schicht an, wenn jemand Stunden arbeitet, die der wiederkehrende Dienstplan nicht abdeckt — ein Samstag, eine Vertretung, ein Schulungsabend.',
];
