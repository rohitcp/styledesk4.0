<?php

declare(strict_types=1);

/*
| Réglages → Personnel → Règles de service.
|
| Une règle de service est un motif de travail réutilisable ; un planning est ce
| qui en applique une à une personne. La copie est volontairement formulée
| autour de cette distinction, parce que c'est là que les lecteurs se trompent.
*/

return [

    'title' => 'Règles de service',
    'intro' => 'Des motifs de travail réutilisables. Une règle est un modèle — l’attribuer à quelqu’un, et modifier un jour qu’il travaille réellement, se fait depuis Personnel → Planning.',
    'add' => 'Ajouter une règle',
    'add_title' => 'Ajouter une règle de service',
    'edit_title' => 'Modifier la règle de service',
    'saving' => 'Enregistrement…',
    'save' => 'Enregistrer la règle',
    'save_and_add_another' => 'Enregistrer et en ajouter une autre',

    'created' => ':name enregistrée.',
    'updated' => ':name mise à jour.',
    'deleted' => 'Règle de service supprimée.',
    'duplicated' => ':name copiée. Donnez à la copie son propre nom et enregistrez-la.',
    'copy_of' => ':name (copie)',
    'made_active' => ':name est active.',
    'made_inactive' => ':name n’est plus proposée pour les nouveaux plannings.',

    'sections' => [
        'basics' => 'Informations de base',
        'days' => 'Jours et heures de travail',
        'break' => 'Pause',
        'limits' => 'Règles d’heures',
        'flexibility' => 'Souplesse',
        'dates' => 'Dates d’effet',
        'split' => 'Réglages des journées coupées',
    ],

    'fields' => [
        'name' => 'Nom de la règle',
        'name_placeholder' => 'Temps plein standard',
        'description' => 'Description',
        'description_placeholder' => 'Quand ou pourquoi utiliser cette règle.',
        'location_scope' => 'S’applique à',
        'locations' => 'Établissements',
        'locations_placeholder' => 'Rechercher ou sélectionner des établissements',
        'status' => 'Statut',

        'break_type' => 'Pause',
        'break_minutes' => 'Durée de la pause',
        'break_starts_at' => 'Début de la pause',
        'break_ends_at' => 'Fin de la pause',

        'max_hours_per_day' => 'Heures maximum par jour',
        'max_hours_per_week' => 'Heures maximum par semaine',
        'min_hours_per_shift' => 'Heures minimum par service',
        'max_hours_per_shift' => 'Heures maximum par service',
        'min_rest_hours' => 'Repos minimum entre deux services',
        'min_rest_hours_hint' => 'Empêche un planning qui finit à 23 h et reprend à 6 h.',
        'max_consecutive_days' => 'Jours travaillés consécutifs maximum',

        'allow_overtime' => 'Autoriser les heures supplémentaires',
        'overtime_after_hours' => 'Heures supplémentaires au-delà de',
        'max_overtime_hours' => 'Heures supplémentaires maximum',
        'allow_adjustment' => 'Autoriser l’ajustement du planning',
        'allow_adjustment_hint' => 'Un responsable peut déplacer un service généré sans modifier cette règle.',
        'allow_split_shift' => 'Autoriser les journées coupées',
        'allow_split_shift_hint' => 'Permet à un salarié de travailler plusieurs plages distinctes le même jour.',
        'allow_same_employee_multiple_periods' => 'Autoriser plusieurs plages pour la même personne',
        'allow_same_employee_multiple_periods_hint' => 'La même personne peut travailler plus d’une plage par jour, à condition qu’elles ne se chevauchent pas.',
        'max_periods_per_employee_per_day' => 'Plages maximum par personne et par jour',
        'min_gap_minutes' => 'Écart minimum entre deux plages',
        'min_gap_hint' => 'Temps non travaillé exigé entre deux plages assurées par la même personne.',
        'minutes_unit' => 'minutes',

        'effective_from' => 'En vigueur à partir du',
        'effective_until' => 'En vigueur jusqu’au',
        'effective_hint' => 'Laissez les deux vides pour une règle toujours en vigueur.',

        'hours_unit' => 'heures',
        'hours_per_week' => 'heures / semaine',
        'days_unit' => 'jours',
    ],

    'columns' => [
        'day' => 'Jour',
        'business_hours' => 'Horaires d’ouverture',
        'name' => 'Règle',
        'location' => 'Établissement',
        'days' => 'Jours travaillés',
        'hours' => 'Heures par défaut',
        'weekly' => 'Heures hebdomadaires',
        'overtime' => 'Heures sup.',
        'status' => 'Statut',
        'assigned' => 'Personnel',
        'updated' => 'Dernière mise à jour',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'location_scopes' => [
        'all' => 'Tous les établissements',
        'specific' => 'Établissements précis',
    ],

    'break_types' => [
        'none' => 'Aucune pause',
        'fixed' => 'Pause fixe',
        'duration' => 'Durée seulement',
    ],

    'break_custom' => 'Personnalisée',
    'minutes' => ':count min',
    'hours_vary' => 'Variable selon le jour',
    'no_working_days' => 'Aucun jour travaillé',
    'all_locations' => 'Tous les établissements',
    'overtime_allowed' => 'Autorisées',
    'overtime_not_allowed' => 'Non autorisées',
    'not_set' => 'Non défini',

    'assigned_staff' => 'Personnel rattaché',
    'assigned_count' => '{0} Personne sur cette règle pour l’instant|{1} :count membre|[2,*] :count membres',
    'assigned_where' => 'On rattache quelqu’un à une règle depuis le formulaire d’ajout ou de modification d’un membre. Générer son planning daté se fait dans Personnel → Planning.',

    'duplicate' => 'Dupliquer',
    'activate' => 'Activer',
    'deactivate' => 'Désactiver',
    'deactivate_confirm' => 'Désactiver :name ? Elle reste lisible sur les plannings qui l’utilisent déjà, mais n’est plus proposée pour les nouveaux.',
    'delete_confirm' => 'Supprimer :name ? Cette règle sera définitivement retirée.',
    'delete_blocked' => ':name est utilisée par :count membres, elle ne peut donc pas être supprimée. Désactivez-la — les plannings qui s’en servent ont besoin qu’elle reste lisible.',

    'validation' => [
        'period_name_required' => 'Donnez un nom à chaque plage de service.',
        'period_times_required' => 'Donnez une heure de début et de fin à chaque plage.',
        'period_ends_after_starts' => 'Une plage doit se terminer après son début.',
        'period_outside_business_hours' => 'La plage doit se situer dans les heures de travail de l’entreprise.',
        'periods_required' => 'Ajoutez au moins une plage, ou désactivez les journées coupées.',
        'period_break_too_long' => 'La pause est plus longue que la plage.',
        'name_required' => 'Donnez un nom à la règle.',
        'name_taken' => 'Une règle portant ce nom existe déjà.',
        'days_required' => 'Choisissez au moins un jour travaillé.',
        'ends_after_starts' => 'L’heure de fin doit être postérieure à l’heure de début.',
        'periods_overlap' => 'Les plages de travail du :day se chevauchent.',
        'split_not_allowed' => 'Cette règle n’autorise pas les journées coupées : le :day ne peut avoir qu’une plage.',
        'break_too_long' => 'La pause est plus longue que la plus courte journée de travail.',
        'break_minutes_required' => 'Choisissez la durée de la pause.',
        'break_times_required' => 'Donnez un début et une fin à la pause fixe.',
        'weekly_hours_positive' => 'Les heures maximum par semaine doivent être supérieures à zéro.',
        'min_shift_over_max' => 'Les heures minimum par service ne peuvent pas dépasser le maximum.',
        'until_before_from' => 'La date de fin d’effet ne peut pas précéder la date de début.',
        'locations_required' => 'Choisissez au moins un établissement, ou appliquez la règle partout.',
    ],

    'results' => [
        'zero' => 'Aucune règle trouvée',
        'one' => '1 règle trouvée',
        'many' => ':count règles trouvées',
        'empty' => 'Aucune règle ne correspond à votre recherche ou à vos filtres.',
        'clear' => 'Effacer les filtres',
    ],
    'showing' => 'Affichage de :from à :to sur :total règles',
    'none_yet' => 'Aucune règle de service',
    'none_yet_hint' => 'Une règle de service est un motif de travail que vous écrivez une fois et appliquez à autant de personnes que vous voulez — « Temps plein standard », « Service du week-end », « Temps partiel matin ».',

    'weekdays_short' => [
        0 => 'Dim',
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Jeu',
        5 => 'Ven',
        6 => 'Sam',
    ],

    'filters' => [
        'all_statuses' => 'Tous les statuts',
        'all_locations' => 'Tous les établissements',
    ],

    /* L'éditeur de semaine est le même composant que celui des horaires
       d'établissement, seuls les mots qui diffèrent sont remplacés : un motif a
       des jours travaillés et des jours de repos, là où une succursale est
       ouverte ou fermée. */
    'hours_editor' => [
        'open' => 'Travaille',
        'closed' => 'Repos',
        'closed_all_day' => 'Ne travaille pas',
        'add_period' => 'Ajouter une plage',
        'opening_time' => 'Heure de début du :day',
        'closing_time' => 'Heure de fin du :day',
    ],

    'hours_are_global' => 'Les heures de travail de l’entreprise servent au planning et à la réservation. Les changer ici les change partout.',
    'edit_business_hours' => 'Modifier les heures de travail',
    'no_location_yet' => 'Ajoutez un établissement avant d’écrire une règle — une semaine de travail appartient à une succursale.',
    'closed' => 'Fermé',

    'sections_split' => 'Réglages des journées coupées',
    'split_intro' => 'Divisez la journée en plages nommées pour couvrir le service. Chaque plage doit se situer dans les heures de travail.',

    'periods' => [
        'name' => 'Nom de la plage',
        'name_placeholder' => 'Service du matin',
        'starts_at' => 'Heure de début',
        'ends_at' => 'Heure de fin',
        'break' => 'Pause',
        'no_break' => 'Aucune pause',
        'status' => 'Statut',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'add' => '+ Ajouter une plage',
        'remove' => 'Retirer',
        'minutes' => ':count min',
        'empty' => 'Aucune plage pour l’instant. Ajoutez la première pour diviser la journée.',
    ],

    'enable' => 'Activer les règles de service',
    'enable_hint' => 'Active les règles de planning réutilisables pour cette entreprise.',
    'feature_on' => 'Les règles de service sont activées.',
    'feature_off' => 'Les règles de service sont désactivées. Rien n’a été supprimé.',
    'feature_off_title' => 'Les règles de service sont désactivées',
    'feature_off_kept' => '{0} Activez-les pour écrire un motif de travail applicable à autant de personnes que vous voulez.|{1} Votre :count règle est toujours là — activez les règles de service pour la voir.|[2,*] Vos :count règles sont toujours là — activez les règles de service pour les voir.',
    'rule_count' => '{0} Règles de service|{1} :count règle de service|[2,*] :count règles de service',
    'back_to_list' => 'Retour aux règles',
    'delete_title' => 'Supprimer la règle de service ?',
];
