<?php

declare(strict_types=1);

/*
| Was das Anmeldeformular sagt, wenn es ablehnt.
|
| Überschreibt die Datei des Frameworks, damit die Wortwahl die von StyleDesk
| ist. 'failed' antwortet bewusst dasselbe auf ein falsches Passwort wie auf
| eine Adresse ohne Konto: Beides zu unterscheiden hieße, einer fremden Person
| zu verraten, welche Adressen hier ein Konto haben.
*/

return [
    'failed' => 'E-Mail-Adresse oder Passwort ist falsch. Bitte erneut versuchen.',

    /* Wenn der Versuch nicht abgelehnt wurde, sondern fehlschlug — siehe
       App\Http\Middleware\ReportSignInFailures. Sagt bewusst nichts darüber,
       was kaputt war: Die lesende Person kann damit nichts anfangen, das
       Protokoll schon. */
    'unavailable' => 'Wir konnten Sie gerade nicht anmelden. Bitte erneut versuchen.',

    'unverified' => 'Ihre E-Mail-Adresse wurde nicht bestätigt. Bitte bestätigen Sie sie, um fortzufahren.',
    'password' => 'Dieses Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte in :seconds Sekunden erneut versuchen.',

    /* Klar gesagt. Wessen Betrieb abgeschaltet wurde, soll das vom Bildschirm
       erfahren und nicht von einem Passwort, das auf einmal nicht mehr
       funktioniert. */
    'business_disabled' => 'Ihr StyleDesk-Konto ist derzeit deaktiviert. Bitte wenden Sie sich an den Support.',
];
