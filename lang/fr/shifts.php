<?php

declare(strict_types=1);

/*
| L'écran Services : les heures de travail sur une date nommée.
|
| Volontairement formulé par contraste avec le planning qu'il côtoie — un
| planning est le motif récurrent, un service un bloc daté — parce que les deux
| se confondent facilement et que c'est la copie qui fait réellement la
| distinction.
*/

return [

    'title' => 'Services',
    'intro' => 'Les heures de travail sur une date donnée. Un service remplace le planning récurrent pour ce jour-là.',
    'add' => 'Ajouter un service',
    'add_title' => 'Ajouter un service',
    'edit_title' => 'Modifier le service',
    'adding' => 'Ajout en cours…',
    'saving' => 'Enregistrement…',
    'save' => 'Enregistrer le service',
    'save_and_add_another' => 'Enregistrer et en ajouter un autre',

    'created' => 'Service ajouté pour :name.',
    'updated' => 'Service mis à jour.',
    'deleted' => 'Service retiré.',
    'delete_confirm' => 'Retirer ce service de :name le :date ?',
    'correct_fields' => 'Vérifiez les champs signalés et réessayez.',

    'fields' => [
        'staff' => 'Membre de l’équipe',
        'staff_placeholder' => 'Rechercher ou sélectionner un membre',
        'location' => 'Établissement',
        'location_placeholder' => 'Rechercher ou sélectionner un établissement',
        'date' => 'Date',
        'starts_at' => 'Heure de début',
        'ends_at' => 'Heure de fin',
        'break' => 'Pause',
        'break_hint' => 'Temps non payé à l’intérieur du service.',
        'type' => 'Type de service',
        'status' => 'Statut',
        'notes' => 'Notes',
        'notes_placeholder' => 'Ce que devrait savoir quiconque lit le planning.',
    ],

    'columns' => [
        'staff' => 'Membre',
        'date' => 'Date',
        'hours' => 'Heures',
        'break' => 'Pause',
        'location' => 'Établissement',
        'type' => 'Type',
        'status' => 'Statut',
    ],

    'filters' => [
        'all_staff' => 'Toute l’équipe',
        'all_locations' => 'Tous les établissements',
        'all_types' => 'Tous les types',
        'all_statuses' => 'Tous les statuts',
        'from' => 'Du',
        'until' => 'Au',
    ],

    'types' => [
        'regular' => 'Normal',
        'overtime' => 'Heures supplémentaires',
        'cover' => 'Remplacement',
        'training' => 'Formation',
        'on-call' => 'Astreinte',
        'custom' => 'Personnalisé',
    ],

    'statuses' => [
        'scheduled' => 'Planifié',
        'confirmed' => 'Confirmé',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ],

    'minutes' => ':count min',
    'no_break' => 'Aucune',

    'view' => 'Voir le service',
    'duplicate' => 'Dupliquer',
    'cancel_shift' => 'Annuler le service',
    'cancel_confirm' => 'Annuler ce service ? Il reste au planning marqué annulé, pour que chacun voie qu’il a été retiré.',
    'cancelled_toast' => 'Service annulé.',

    'validation' => [
        'business_closed' => 'L’établissement est fermé le :day, personne ne peut donc y être planifié. Changez de jour, ou ouvrez-le dans Réglages → Entreprise → Horaires de travail.',
        'outside_business_hours' => 'Ces heures tombent en dehors des heures d’ouverture (:hours).',
        'ends_after_start' => 'L’heure de fin doit être postérieure à l’heure de début.',
        'break_too_long' => 'La pause est plus longue que le service.',
        'clash' => ':name a déjà ce jour-là un service qui chevauche ces heures.',
        'staff_required' => 'Choisissez qui assure ce service.',
        'date_required' => 'Choisissez le jour de ce service.',
    ],

    'results' => [
        'zero' => 'Aucun service trouvé',
        'one' => '1 service trouvé',
        'many' => ':count services trouvés',
        'empty' => 'Aucun service ne correspond à votre recherche ou à vos filtres.',
        'clear' => 'Effacer les filtres',
    ],
    'showing' => 'Affichage de :from à :to sur :total services',
    'none_yet' => 'Aucun service pour l’instant',
    'none_yet_hint' => 'Ajoutez un service quand quelqu’un travaille des heures que son planning récurrent ne couvre pas — un samedi, un remplacement, une soirée de formation.',
];
