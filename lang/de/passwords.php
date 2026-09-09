<?php

declare(strict_types=1);

/*
| Was der Passwort-Broker sagt.
|
| Überschreibt die eigene Datei des Frameworks, damit die Wortwahl die von
| StyleDesk ist. Nur der Text ändert sich — welche Zeile wann gewählt wird,
| bleibt die Entscheidung des Brokers.
*/

return [
    'reset' => 'Ihr Passwort wurde zurückgesetzt. Sie können sich jetzt damit anmelden.',

    /*
    | Wird gesagt, ganz gleich ob die Adresse zu einem Konto gehört — siehe die
    | Anmerkung zu 'user' weiter unten — darf also nicht versprechen, dass
    | gerade dieser Person eine E-Mail zugeht. „Falls wir ein Konto haben“
    | leistet das.
    */
    'sent' => 'Falls wir ein Konto für diese Adresse haben, ist ein Link zum Zurücksetzen unterwegs. Folgen Sie den Anweisungen in dieser E-Mail, um ein neues Passwort zu setzen.',

    'throttled' => 'Sie haben vor Kurzem einen Link angefordert. Bitte warten Sie einen Moment.',

    'token' => 'Dieser Link zum Zurücksetzen ist ungültig oder wurde bereits verwendet. Fordern Sie unten einen neuen an.',

    /*
    | Wird nie erreicht: FortifyServiceProvider beantwortet eine unbekannte
    | Adresse ebenfalls mit der Zeile 'sent', denn ein Formular, das sagt „diese
    | Person kennen wir nicht“, ist eine Art zu fragen, welche Adressen ein
    | Konto haben. Bleibt, weil der Vertrag des Brokers den Schlüssel erwartet.
    */
    'user' => 'Falls wir ein Konto für diese Adresse haben, ist ein Link zum Zurücksetzen unterwegs.',
];
