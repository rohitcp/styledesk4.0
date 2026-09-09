<?php

declare(strict_types=1);

/*
| Die Systemrollen.
|
| StyleDesks eigenes Vokabular, nach dem Rollenschlüssel indexiert statt nach
| dem gespeicherten Namen — damit eine Rolle, die ein Betrieb selbst angelegt
| und benannt hat, ihre eigenen Worte behält, genau wie ein Leistungsname oder
| eine Kundennotiz.
|
| Jeder englische Wert ist der Name, den config/role_defaults.php ohnehin trug.
*/

return [
    'owner' => [
        'name' => 'Inhaber:in',
        'description' => 'Vollzugriff auf Betrieb, Personal, Einstellungen, Abrechnung und Betriebsdaten.',
    ],
    'administrator' => [
        'name' => 'Admin',
        'description' => 'Vollständiger operativer und administrativer Zugriff, ausgenommen geschützte Aktionen, die nur der Inhaberschaft vorbehalten sind.',
    ],
    'manager' => [
        'name' => 'Leitung',
        'description' => 'Tagesgeschäft, Personal, Leistungen, Kundschaft und Auswertungen für die eigenen Standorte.',
    ],
    'front-desk' => [
        'name' => 'Empfang',
        'description' => 'Termine, Kundschaft, Buchungen, Check-in und Check-out sowie Aufgaben am Empfang.',
    ],
    'service-provider' => [
        'name' => 'Fachkraft',
        'description' => 'Der eigene Kalender, die eigenen Termine sowie zugewiesene Kundschaft und Leistungen.',
    ],

    /*
    | Die Zahlen unter dem Namen einer Rolle.
    |
    | Ganze Wendungen, von der Sprachdatei pluralisiert statt von
    | Str::plural(), das nur Englisch kann und „2 miembro del personals“
    | geschrieben hätte.
    */
    'permissions_summary' => ':granted von :total Rechten',
    'staff_summary' => '{0} Keine Mitarbeitenden|{1} :count Person|[2,*] :count Personen',
];
