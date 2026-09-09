<?php

declare(strict_types=1);

/*
| Das Modul Öffnungszeiten: die Übersicht und der Editor je Standort.
|
| Die Karte mit den Wochenzeiten teilt sich das Modul mit den Standorten und
| liest ihre Texte aus der locations-Datei — derselbe Editor ist also überall
| gleich formuliert, von wo aus man ihn auch öffnet. (Ausgeschrieben statt als
| Pfad: Ein Muster, das auf Stern-Schrägstrich endet, schließt den Kommentar,
| in dem es steht.)
*/

return [
    'title' => 'Öffnungszeiten',
    'intro' => 'Die Öffnungszeiten jedes Standorts sowie die Feiertage, Schließungen und Sonderzeiten, die sie überschreiben. Die Zeiten erscheinen in der jeweils eigenen Zeitzone des Standorts.',
    'timezone_note' => 'Die Zeiten gelten in der eigenen Zeitzone dieses Standorts, :name (:identifier).',

    'edit_hours' => 'Zeiten bearbeiten',
    'save' => 'Zeiten speichern',
    'saved' => 'Öffnungszeiten gespeichert.',
    'saved_from' => 'Zeiten gespeichert, gültig ab :date.',
    'also_applied' => '{1} Auch auf :count weiteren Standort angewendet.|[2,*] Auch auf :count weitere Standorte angewendet.',
    'schedule_discarded' => 'Kommende Zeiten verworfen.',
    'correct_fields' => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',

    'no_locations' => 'Noch keine Standorte.',
    'no_locations_hint' => 'Öffnungszeiten gehören zu einem Standort — legen Sie also zuerst einen an.',
    'add_location' => 'Standort anlegen',

    'upcoming' => [
        'title' => 'Kommt',
        'hint' => 'Feiertage, Schließungen und Sonderzeiten in den nächsten 12 Monaten.',
        'in_progress' => 'Läuft gerade',
        'summary' => '{1} :count kommende Ausnahme|[2,*] :count kommende Ausnahmen',
        'and_more' => 'und :count weitere',
    ],

    'future' => [
        'starts' => 'Neue Zeiten beginnen am :date.',
        'review' => 'Ansehen',
        'editing' => 'Sie bearbeiten Zeiten, die am :date beginnen. Die heutigen bleiben unverändert.',
        'edit_today' => 'Stattdessen die heutigen Zeiten bearbeiten',
        'pending' => 'Andere Zeiten beginnen am :date. Änderungen hier gelten bis dahin.',
        'edit_those' => 'Stattdessen jene bearbeiten',
        'discard' => 'Verwerfen',
        'discard_confirm' => 'Diese kommenden Zeiten verwerfen? Die aktuellen gelten dann weiter.',
    ],

    'effective' => [
        'title' => 'Ab wann diese Zeiten gelten',
        'hint' => 'Leer lassen, um die derzeit geltenden Zeiten zu ändern. Wählen Sie ein Datum, um eine Änderung vorzuplanen — bis dahin gelten die aktuellen Zeiten weiter.',
        'label' => 'Gültig ab',
    ],

    'apply' => [
        'title' => 'Auf andere Standorte anwenden',
        'hint' => 'Kopiert diese Woche auf die angehakten Filialen. Jede behält danach ihre eigene Kopie, Sie können also eine ändern, ohne die anderen anzurühren.',
        'warning' => 'Das ersetzt die Zeiten der angehakten Standorte für denselben Zeitraum.',
    ],

    'exceptions' => [
        'title' => 'Feiertage, Schließungen und Sonderzeiten',
        'hint' => 'Termine, die die Wochenzeiten oben überschreiben.',
        'add' => 'Datum hinzufügen',
        'empty' => 'Nichts eingeplant. Fügen Sie einen Feiertag, eine Schließung oder einen Tag mit anderen Zeiten hinzu.',
        'add_title' => 'Datum hinzufügen',
        'edit_title' => 'Dieses Datum bearbeiten',
        'save' => 'Datum speichern',
        'added' => 'Zum Kalender hinzugefügt.',
        'updated' => 'Kalendereintrag aktualisiert.',
        'removed' => 'Aus dem Kalender entfernt.',
        'delete_confirm' => '„:name“ aus dem Kalender entfernen?',

        'type' => 'Worum handelt es sich',
        'name' => 'Name',
        'name_placeholder' => 'Weihnachten',
        'from' => 'Von',
        'to' => 'Bis',
        'to_hint' => 'Für einen einzelnen Tag leer lassen.',
        'closed_all_day' => 'Ganztägig geschlossen',
        'closed_all_day_hint' => 'Schalten Sie das ab, um stattdessen mit anderen Zeiten zu öffnen.',
        'opens' => 'Öffnet',
        'closes' => 'Schließt',
        'notes' => 'Interne Notiz',
        'notes_placeholder' => 'Nur Ihr Team sieht das.',
    ],

    'validation' => [
        'name_required' => 'Geben Sie dem einen Namen, damit das Team weiß, worum es geht.',
        'date_required' => 'Wählen Sie ein Datum.',
        'end_before_start' => 'Das Enddatum kann nicht vor dem Startdatum liegen.',
        'opens_required' => 'Geben Sie die Öffnungszeit an, oder markieren Sie den Tag als geschlossen.',
        'closes_required' => 'Geben Sie die Schließzeit an, oder markieren Sie den Tag als geschlossen.',
        'closes_after_opens' => 'Die Schließzeit muss nach der Öffnungszeit liegen.',
        'effective_after' => 'Künftige Zeiten müssen an einem späteren Datum beginnen. Leer lassen, um die heutigen zu ändern.',
        'schedule_not_future' => 'Nur Zeiten, die noch nicht begonnen haben, lassen sich verwerfen.',
        'clash' => '„:name“ deckt bereits :dates ab. Bearbeiten Sie stattdessen diesen Eintrag, oder wählen Sie andere Daten.',
    ],

    /*
     * Die Arten von Ausnahmen.
     *
     * Nach dem in location_closures gespeicherten Wert verschlüsselt, damit
     * Auswahlfeld und Validierungsregel dieselbe Liste lesen.
     */
    'types' => [
        'public_holiday' => 'Feiertag',
        'closure' => 'Schließung des Standorts',
        'special_hours' => 'Sonderöffnungszeiten',
        'training' => 'Schulungstag',
        'maintenance' => 'Schließung wegen Wartung',
        'private_event' => 'Private Veranstaltung',
        'emergency' => 'Notfallschließung',
    ],
];
