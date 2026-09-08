<?php

declare(strict_types=1);

/**
 * Was die Speicherkomponente sagt, wenn sie eine Datei ablehnt.
 *
 * Jede Meldung nennt die Grenze statt den Fehler: „Dieses Bild ist größer als
 * 5 MB“ sagt der lesenden Person, was als Nächstes zu tun ist — „Upload
 * fehlgeschlagen“ sagt nur, dass etwas schiefging.
 */
return [
    'upload_failed' => 'Diese Datei konnte nicht hochgeladen werden. Bitte erneut versuchen.',
    'extension_not_allowed' => 'Diese Art von Datei wird hier nicht angenommen. Erlaubt: :list.',
    'type_not_allowed' => 'Diese Datei ist nicht von der Art, die sie vorgibt zu sein, und wurde deshalb nicht gespeichert.',
    'too_large' => 'Diese Datei ist größer als :size.',
    'not_found' => 'Diese Datei ist nicht mehr verfügbar.',
];
