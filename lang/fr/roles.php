<?php

declare(strict_types=1);

/*
| Les rôles système.
|
| Le vocabulaire propre à StyleDesk, indexé par la clé du rôle plutôt que par
| son nom enregistré — ainsi un rôle qu'une entreprise a créé et nommé garde
| ses propres mots, exactement comme un nom de prestation ou une note client.
|
| Chaque valeur anglaise est le nom que config/role_defaults.php portait déjà.
*/

return [
    'owner' => [
        'name' => 'Propriétaire',
        'description' => 'Accès complet à l’entreprise, à l’équipe, aux réglages, à la facturation et aux données d’exploitation.',
    ],
    'administrator' => [
        'name' => 'Administrateur',
        'description' => 'Accès complet à l’exploitation et à l’administration, hors actions protégées réservées au propriétaire.',
    ],
    'manager' => [
        'name' => 'Responsable',
        'description' => 'Exploitation quotidienne, équipe, prestations, clients et rapports pour ses établissements.',
    ],
    'front-desk' => [
        'name' => 'Réceptionniste',
        'description' => 'Rendez-vous, clients, réservations, arrivées et départs, et activités d’accueil.',
    ],
    'service-provider' => [
        'name' => 'Praticien',
        'description' => 'Son propre agenda, ses rendez-vous, ses clients et prestations assignés.',
    ],

    /*
    | Les chiffres sous le nom d'un rôle.
    |
    | Phrases entières mises au pluriel par le fichier de langue plutôt que par
    | Str::plural(), qui ne connaît que l'anglais et aurait écrit
    | « 2 miembro del personals ».
    */
    'permissions_summary' => ':granted permissions sur :total',
    'staff_summary' => '{0} Aucun membre|{1} :count membre|[2,*] :count membres',
];
