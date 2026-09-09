<?php

declare(strict_types=1);

/*
| Das Währungsmodul.
|
| Die *Namen* der Währungen stehen nicht hier: Sie liegen in
| config/currencies.php neben Symbol und Formatregeln, weil der Name einer
| Währung Teil ihrer Definition ist und nicht Oberflächentext. „US Dollar“ ist,
| wie diese Währung heißt; sie je Sprache zu übersetzen ließe Code und Namen
| darüber uneins werden, welches Geld gemeint ist.
*/

return [
    'title' => 'Währung',
    'intro' => 'Die Währung, in der Ihre Preise stehen, und alle weiteren Währungen, in denen Sie ebenfalls Preise angeben.',
    'edit' => 'Währungen bearbeiten',
    'saved' => 'Währungseinstellungen aktualisiert.',

    'primary' => 'Hauptwährung',

    'is_primary' => 'primär',
    'primary_hint' => 'Die Voreinstellung für Leistungen, Produkte, Pakete, Mitgliedschaften, Anzahlungen, Gebühren, Rabatte, Steuern, Zahlungen, Erstattungen und Auswertungen.',
    'secondary' => 'Weitere Währungen',
    'secondary_hint' => 'Währungen, in denen Sie ebenfalls Preise angeben. Die Hauptwährung steht immer zur Verfügung und wird hier nicht aufgeführt.',
    'enabled' => 'Aktive Währungen',
    'format' => 'Wie Preise dargestellt werden',
    'format_hint' => 'Von der Währung bestimmt, nicht von Ihnen — Symbol, seine Position, die Trennzeichen und die Zahl der Nachkommastellen.',

    'single_currency' => 'Sie kalkulieren in einer Währung. Fügen Sie eine weitere hinzu, dann fragen Preisfelder nach einem Preis je Währung.',

    'no_conversion' => 'Preise werden nicht zwischen Währungen umgerechnet. Sie setzen den Preis in jeder selbst, damit ein über Nacht gewanderter Kurs nie ändert, was jemandem genannt wurde.',
    'scope_note' => 'Ein Wechsel der Hauptwährung kalkuliert nichts neu. Bestehende Preise behalten die Währung, in der sie erfasst wurden.',

    'validation' => [
        'primary_required' => 'Wählen Sie eine Hauptwährung.',
        'unsupported' => 'Diese Währung steht nicht zur Verfügung.',
    ],
];
