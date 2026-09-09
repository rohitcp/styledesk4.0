<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gründe
|--------------------------------------------------------------------------
|
| Warum etwas geschehen ist — aus einer Liste gewählt statt getippt. Die
| Listen selbst stehen in config/reasons.php; hier steht nur, wie sie heißen.
|
*/

return [

    'title' => 'Gründe',
    'intro' => 'Warum Dinge passiert sind, als Liste statt als Freitextfeld — damit „Warum verlieren wir Buchungen?“ eine Frage ist, die die Auswertungen wirklich beantworten können.',
    'back' => 'Alle Gründelisten',

    'types' => [
        'booking-cancellation' => ['label' => 'Buchungsabsage', 'intro' => 'Warum ein Termin abgesagt wurde.'],
        'booking-reschedule' => ['label' => 'Terminverschiebung', 'intro' => 'Warum ein Termin verschoben wurde.'],
        'no-show' => ['label' => 'Nicht erschienen', 'intro' => 'Warum jemand nicht gekommen ist.'],
        'refund' => ['label' => 'Erstattung', 'intro' => 'Warum Geld zurückgegangen ist.'],
        'payment-adjustment' => ['label' => 'Zahlungsanpassung', 'intro' => 'Warum eine Rechnung nachträglich geändert wurde.'],
        'client-status-change' => ['label' => 'Änderung des Kundenstatus', 'intro' => 'Warum jemand zwischen aktiv, inaktiv und dem Rest gewechselt ist.'],
        'staff-schedule-change' => ['label' => 'Dienstplanänderung', 'intro' => 'Warum ein Dienstplan geändert wurde — Schichten, Stunden, Urlaub und Vertretung.'],
        'booking-declined' => ['label' => 'Buchung abgelehnt', 'intro' => 'Warum eine Terminanfrage abgelehnt wurde.'],
        'service-cancellation' => ['label' => 'Leistung eingestellt', 'intro' => 'Warum eine Leistung nicht mehr angeboten wird.'],
    ],

    'columns' => [
        'reason' => 'Grund',
        'source' => 'Herkunft',
        'details' => 'Fragt nach dem Warum',
        'status' => 'Status',
        'action' => 'Aktion',
    ],

    'system' => 'StyleDesk',
    'custom' => 'Eigene',
    'renamed' => 'Umbenannt',
    'active' => 'An',
    'inactive' => 'Aus',
    'activate' => 'Einschalten',
    'deactivate' => 'Ausschalten',
    'edit' => 'Bearbeiten',
    'delete' => 'Grund löschen',
    'delete_confirm' => '„:name“ löschen? Alles, was bereits darunter erfasst ist, behält seinen Grund; neu ablegen lässt sich darunter nichts mehr.',
    'system_undeletable' => 'Diesen liefert StyleDesk. Er lässt sich umbenennen oder abschalten, aber nicht löschen — die darunter abgelegten Vorgänge müssen weiterhin sagen, warum.',

    'add' => 'Grund hinzufügen',
    'add_title' => 'Grund hinzufügen',
    'edit_title' => 'Grund bearbeiten',
    'name' => 'Grund',
    'name_placeholder' => 'Kundin oder Kunde hat es sich anders überlegt',
    'description' => 'Beschreibung',
    'description_hint' => 'Optional. Was dieser hier bedeutet — für die nächste Person, die ihn wählt.',
    'requires_details' => 'Bei dieser Wahl nach einer Erklärung fragen',
    'requires_details_hint' => 'Für Gründe, die für sich allein keine Antwort sind — „Sonstiges“ ist der offensichtliche Fall.',
    'details_label' => 'Weitere Angaben',

    'extra_title' => 'Wird ebenfalls gefragt',
    'extra_hint' => 'Diese Liste stellt neben dem Grund eine zweite Frage. Sie gehört dazu, wie :label erfasst wird, und ist nicht konfigurierbar.',

    'counts' => ':active von :total eingeschaltet',
    'reorder_hint' => 'Zum Sortieren ziehen',
    'save_order' => 'Reihenfolge speichern',
    'order_changed' => 'Die Reihenfolge hat sich geändert, ist aber noch nicht gespeichert.',

    'added' => 'Grund hinzugefügt.',
    'updated' => 'Grund aktualisiert.',
    'activated' => 'Grund eingeschaltet.',
    'deactivated' => 'Grund ausgeschaltet. Alles bereits darunter Erfasste bleibt unberührt.',
    'deleted' => 'Grund gelöscht.',
    'order_saved' => 'Reihenfolge gespeichert.',
    'none' => 'Noch keine Gründe in dieser Liste.',
];
