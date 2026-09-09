<?php

declare(strict_types=1);

/**
 * Ce que dit le composant de stockage lorsqu'il refuse un fichier.
 *
 * Chaque message nomme la limite plutôt que l'échec : « cette image dépasse
 * 5 Mo » dit au lecteur quoi faire ensuite, là où « échec de l'envoi » lui dit
 * seulement que quelque chose a mal tourné.
 */
return [
    'upload_failed' => 'Ce fichier n’a pas pu être envoyé. Réessayez.',
    'extension_not_allowed' => 'Ce type de fichier n’est pas accepté ici. Autorisés : :list.',
    'type_not_allowed' => 'Ce fichier n’est pas du type qu’il prétend être ; il n’a pas été enregistré.',
    'too_large' => 'Ce fichier dépasse :size.',
    'not_found' => 'Ce fichier n’est plus disponible.',
];
