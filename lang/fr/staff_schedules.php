<?php

declare(strict_types=1);

/*
| Le tableau des plannings de l'équipe : une ligne par personne, une colonne
| par mois.
|
| Le vocabulaire distingue trois états, parce que tout l'écran existe pour les
| distinguer d'un coup d'œil : Publié est un mois dont la personne a reçu
| l'e-mail, Brouillon un mois planifié mais pas envoyé, et Non planifié un mois
| auquel personne n'a encore pensé.
*/

return [

    'title' => 'Planning de l’équipe',
    'intro' => 'Quels mois sont couverts, lesquels sont encore des brouillons, et qui n’a rien de prévu.',
    'summary' => '{0} Aucun membre|{1} :count membre|[2,*] :count membres',

    'assign' => 'Attribuer un planning',
    'add_shift' => 'Ajouter un service',

    'search_label' => 'Rechercher un membre',
    'search_placeholder' => 'Rechercher par nom, e-mail ou intitulé de poste',

    'filters' => [
        'month' => 'Mois',
        'year' => 'Année',
        'status' => 'Statut du planning',
        'coverage' => 'Couverture',
        'all_statuses' => 'Tous les statuts',
        'all_coverage' => 'Tous les mois',
        'apply' => 'Appliquer',
        'reset' => 'Réinitialiser',
    ],

    'statuses' => [
        'scheduled' => 'Planifié',
        'not-scheduled' => 'Non planifié',
        'draft' => 'Brouillon',
        'published' => 'Publié',
    ],

    'coverage' => [
        'completed' => 'Mois écoulés',
        'current' => 'Mois en cours',
        'future' => 'Mois à venir',
    ],

    /*
    | Ce que dit une case. « Modifications » est le quatrième état que l'écran
    | par personne tient déjà : envoyé une fois puis modifié depuis — un
    | brouillon du point de vue du membre, pas du point de vue de sa boîte mail.
    */
    'states' => [
        'published' => 'Publié',
        'draft' => 'Brouillon',
        'changes' => 'Modifications en attente',
        'not-scheduled' => 'Non planifié',
    ],

    'columns' => [
        'staff' => 'Membre de l’équipe',
    ],

    'summary_line' => 'Mois :month · Année :year',
    'shifts_count' => '{1} :count service|[2,*] :count services',
    'scroll_hint' => 'Faites défiler à gauche et à droite pour voir tous les mois.',
    'this_month' => 'Ce mois-ci',
    'cell_hint' => ':name · :month',
    'open_schedule' => 'Ouvrir le planning de :name pour :month',
    'start_schedule' => 'Attribuer à :name un planning pour :month',

    'menu' => [
        'view' => 'Voir le planning de :month',
        'assign' => 'Attribuer le planning de :month',
    ],

    'actions_for' => 'Actions pour :name',
    'showing' => 'Affichage de :from à :to sur :total membres',
    'results' => [
        'zero' => 'Aucun membre',
        'one' => '1 membre',
        'many' => ':count membres',
        'clear' => 'Effacer les filtres',
    ],
    'empty' => 'Aucun membre ne correspond à ces filtres.',
    'empty_hint' => 'Effacez les filtres pour voir toute l’équipe.',
    'no_months' => 'Aucun mois ne correspond à ce filtre de couverture.',

    'start' => [
        'title' => 'Attribuer un planning',
        'intro' => 'Choisissez à qui s’adresse le planning et quel mois il couvre. Le mois se planifie d’un bloc.',
        'staff' => 'Membre de l’équipe',
        'choose_staff' => 'Rechercher ou sélectionner un membre',
        'continue' => 'Continuer vers le planning',
    ],

    'month' => [
        'date' => 'Date',
        'day' => 'Jour',
        'shift' => 'Service',
        'start' => 'Début',
        'end' => 'Fin',
        'break' => 'Pause',
        'hours' => 'Heures totales',
        'status' => 'Statut',
        'split' => 'Service :index',
        'minutes' => ':count min',
        'edit' => 'Modifier / réattribuer le planning',
        'loading' => 'Chargement…',
        'failed' => 'Ce planning n’a pas pu être chargé.',
    ],

    'legend' => [
        'title' => 'Légende',
        'published' => 'Tout le monde a reçu ce mois par e-mail.',
        'draft' => 'Planifié, mais personne n’a encore été prévenu.',
        'not_scheduled' => 'Rien de prévu — à traiter.',
    ],
];
