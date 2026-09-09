<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trinkgeld
|--------------------------------------------------------------------------
|
| Zwei Fragen, und es sind verschiedene: ob dieser Betrieb Trinkgeld annimmt,
| und welche seiner Leistungen Trinkgeld bekommen. Ein Salon, der seinen
| Fachkräften Trinkgeld gibt, gibt keines dem Regal, aus dem eine Flasche
| Shampoo stammt.
|
*/

return [

    'title' => 'Trinkgeld',
    'intro' => 'Ob Kundschaft nach Trinkgeld gefragt wird, was ihr angeboten wird, und für welche Leistungen das gilt.',
    'back' => 'Einstellungen',

    'enable' => 'Trinkgeld annehmen',
    'enable_hint' => 'Fügt an der Kasse einen Trinkgeldschritt hinzu. Abschalten verbirgt die Frage und behält alles darunter genau so, wie Sie es gelassen haben.',
    'disabled_note' => 'Trinkgeld ist abgeschaltet. Was hier eingestellt ist, bleibt erhalten und kommt zurück, sobald Sie es wieder einschalten.',

    /* Zwei Reiter, sobald Trinkgeld an ist: was vorgeschlagen wird, und für
       welche Leistungen es gilt. */
    'tabs' => [
        'suggest' => 'Was vorgeschlagen wird',
        'services' => 'Leistungen',
    ],

    'defaults' => 'Was vorgeschlagen wird',
    'defaults_hint' => 'Was eine Leistung verwendet, solange sie nichts anderes sagt.',
    'tip_type' => 'Art des Trinkgelds',
    'types' => [
        'percent' => 'Prozentsatz',
        'fixed' => 'Fester Betrag',
    ],
    'default_tip' => 'Standard-Trinkgeld',
    'default_tip_hint' => 'Ein Prozentsatz des trinkgeldfähigen Teils der Rechnung, oder ein fester Betrag.',

    'percentages' => 'An der Kasse angeboten',
    'percentages_hint' => 'Bis zu sechs. Die Kundschaft bekommt zusätzlich ein Feld für einen eigenen Betrag.',

    'require_selection' => 'Kundschaft zur Auswahl auffordern',
    'require_selection_hint' => 'Es muss geantwortet werden, bevor die Zahlung durchgeht. „Kein Trinkgeld“ ist ebenfalls eine Antwort — es ist eine Frage, keine Belastung.',
    'allow_no_tip' => '„Kein Trinkgeld“ anbieten',
    'allow_no_tip_hint' => 'Empfohlen. Ohne das hat die Kundschaft keine Möglichkeit abzulehnen.',

    /* ------------------------------------------------------------ Leistungen */

    'services' => 'Leistungen',
    'services_hint' => 'Welche Leistungen Trinkgeld bekommen und was jede vorschlägt. Leer folgt der Einstellung oben.',
    'columns' => [
        'service' => 'Leistung',
        'category' => 'Kategorie',
        'price' => 'Preis',
        'tips' => 'Trinkgeld',
        'default' => 'Standard-Trinkgeld',
        'type' => 'Art',
        'required' => 'Auswahl nötig',
        'no_tip' => 'Erlaubt „Kein Trinkgeld“',
        'status' => 'Status',
    ],
    'follows_default' => 'Folgt der Voreinstellung',
    'accepted' => 'Angenommen',
    'not_accepted' => 'Kein Trinkgeld',
    'no_services' => 'Noch keine aktiven Leistungen zum Einrichten.',

    'edit_service' => 'Trinkgeldeinstellungen',
    'edit_service_for' => 'Trinkgeld — :name',
    'service_card_hint' => 'Wie Trinkgeld für diese Leistung funktioniert. Es startet mit Ihren Vorgaben unter Einstellungen → Trinkgeld und lässt sich hier ändern, ohne diese anzurühren.',
    'accepts' => 'Für diese Leistung Trinkgeld annehmen',
    'accepts_hint' => 'Aus für alles, woran niemand gearbeitet hat — etwa Verkaufsware.',

    /* ------------------------------------------------------------- die Kasse */

    'panel' => [
        'title' => 'Trinkgeld',
        'eligible' => 'Trinkgeld auf',
        'custom' => 'Eigener Betrag',
        'none' => 'Kein Trinkgeld',
        'selected' => 'Trinkgeld',
        'required' => 'Wählen Sie eine Trinkgeldoption, bevor Sie die Zahlung annehmen.',
        'not_eligible' => 'An dieser Buchung bekommt nichts Trinkgeld.',
    ],

    'saved' => 'Trinkgeldeinstellungen gespeichert.',
    'enabled' => 'Trinkgeld ist an.',
    'disabled' => 'Trinkgeld ist aus. Ihre Einstellungen bleiben erhalten.',
    'service_saved' => 'Trinkgeldeinstellungen der Leistung gespeichert.',
];
