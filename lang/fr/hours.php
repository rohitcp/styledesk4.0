<?php

declare(strict_types=1);

/*
| Le module Horaires d'ouverture : la vue d'ensemble et l'éditeur par
| établissement.
|
| La carte des horaires hebdomadaires est partagée avec le module
| Établissements et lit ses chaînes dans le fichier locations, si bien que le
| même éditeur est formulé à l'identique d'où qu'on l'ouvre. (Écrit en toutes
| lettres plutôt qu'en chemin : un motif finissant par une étoile et une barre
| oblique fermerait le commentaire qui le contient.)
*/

return [
    'title' => 'Horaires d’ouverture',
    'intro' => 'Les horaires de chaque établissement, ainsi que les jours fériés, fermetures et horaires exceptionnels qui les remplacent. Les heures sont affichées dans le fuseau propre à chaque établissement.',
    'timezone_note' => 'Les heures sont dans le fuseau propre à cet établissement, :name (:identifier).',

    'edit_hours' => 'Modifier les horaires',
    'save' => 'Enregistrer les horaires',
    'saved' => 'Horaires d’ouverture enregistrés.',
    'saved_from' => 'Horaires enregistrés, à partir du :date.',
    'also_applied' => '{1} Également appliqués à :count autre établissement.|[2,*] Également appliqués à :count autres établissements.',
    'schedule_discarded' => 'Horaires à venir abandonnés.',
    'correct_fields' => 'Corrigez les champs signalés et réessayez.',

    'no_locations' => 'Aucun établissement pour l’instant.',
    'no_locations_hint' => 'Les horaires appartiennent à un établissement, commencez donc par en ajouter un.',
    'add_location' => 'Ajouter un établissement',

    'upcoming' => [
        'title' => 'À venir',
        'hint' => 'Jours fériés, fermetures et horaires exceptionnels des 12 prochains mois.',
        'in_progress' => 'En cours',
        'summary' => '{1} :count exception à venir|[2,*] :count exceptions à venir',
        'and_more' => 'et :count de plus',
    ],

    'future' => [
        'starts' => 'De nouveaux horaires commencent le :date.',
        'review' => 'Les consulter',
        'editing' => 'Vous modifiez des horaires qui commencent le :date. Ceux d’aujourd’hui sont inchangés.',
        'edit_today' => 'Modifier plutôt les horaires d’aujourd’hui',
        'pending' => 'D’autres horaires commencent le :date. Les changements faits ici s’appliquent jusque-là.',
        'edit_those' => 'Modifier plutôt ceux-là',
        'discard' => 'Abandonner',
        'discard_confirm' => 'Abandonner ces horaires à venir ? Les horaires actuels continueront de s’appliquer.',
    ],

    'effective' => [
        'title' => 'Quand ces horaires commencent',
        'hint' => 'Laissez vide pour modifier les horaires en vigueur. Choisissez une date pour planifier un changement à l’avance — les horaires actuels s’appliquent jusque-là.',
        'label' => 'En vigueur à partir du',
    ],

    'apply' => [
        'title' => 'Appliquer à d’autres établissements',
        'hint' => 'Copie cette semaine vers les succursales cochées. Chacune en garde ensuite sa propre copie, vous pouvez donc en modifier une sans toucher aux autres.',
        'warning' => 'Cela remplace les horaires des établissements cochés pour la même période.',
    ],

    'exceptions' => [
        'title' => 'Jours fériés, fermetures et horaires exceptionnels',
        'hint' => 'Des dates qui remplacent les horaires hebdomadaires ci-dessus.',
        'add' => 'Ajouter une date',
        'empty' => 'Rien de prévu. Ajoutez un jour férié, une fermeture ou une journée aux horaires différents.',
        'add_title' => 'Ajouter une date',
        'edit_title' => 'Modifier cette date',
        'save' => 'Enregistrer la date',
        'added' => 'Ajoutée au calendrier.',
        'updated' => 'Entrée du calendrier mise à jour.',
        'removed' => 'Retirée du calendrier.',
        'delete_confirm' => 'Retirer « :name » du calendrier ?',

        'type' => 'De quoi s’agit-il',
        'name' => 'Nom',
        'name_placeholder' => 'Noël',
        'from' => 'Du',
        'to' => 'Au',
        'to_hint' => 'Laissez vide pour une seule journée.',
        'closed_all_day' => 'Fermé toute la journée',
        'closed_all_day_hint' => 'Désactivez-le pour ouvrir avec des horaires différents.',
        'opens' => 'Ouvre',
        'closes' => 'Ferme',
        'notes' => 'Note interne',
        'notes_placeholder' => 'Seule votre équipe la voit.',
    ],

    'validation' => [
        'name_required' => 'Donnez-lui un nom, pour que l’équipe sache de quoi il s’agit.',
        'date_required' => 'Choisissez une date.',
        'end_before_start' => 'La date de fin ne peut pas précéder la date de début.',
        'opens_required' => 'Indiquez l’heure d’ouverture, ou marquez la journée comme fermée.',
        'closes_required' => 'Indiquez l’heure de fermeture, ou marquez la journée comme fermée.',
        'closes_after_opens' => 'L’heure de fermeture doit être après l’heure d’ouverture.',
        'effective_after' => 'Des horaires futurs doivent commencer à une date ultérieure. Laissez vide pour modifier ceux d’aujourd’hui.',
        'schedule_not_future' => 'Seuls des horaires qui n’ont pas encore commencé peuvent être abandonnés.',
        'clash' => '« :name » couvre déjà :dates. Modifiez cette entrée, ou choisissez d’autres dates.',
    ],

    /*
     * Les types d'exception.
     *
     * Clés identiques à la valeur enregistrée dans location_closures, si bien
     * que la liste déroulante et la règle de validation lisent la même liste.
     */
    'types' => [
        'public_holiday' => 'Jour férié',
        'closure' => 'Fermeture de l’établissement',
        'special_hours' => 'Horaires exceptionnels',
        'training' => 'Journée de formation',
        'maintenance' => 'Fermeture pour maintenance',
        'private_event' => 'Événement privé',
        'emergency' => 'Fermeture d’urgence',
    ],
];
