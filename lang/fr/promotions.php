<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bons et offres
|--------------------------------------------------------------------------
|
| Un seul module pour les deux, parce que c'est une même chose avec une seule
| différence : un bon se saisit, une offre s'applique toute seule.
|
*/

return [

    'title' => 'Bons et offres',
    'intro' => 'Les remises qu’un client saisit, et celles qui s’appliquent d’elles-mêmes.',
    'new' => 'Créer un bon / une offre',
    'none_yet' => 'Aucun bon ni offre pour l’instant.',
    'none_yet_hint' => 'Une remise première visite est celle par laquelle commencent la plupart des salons.',
    'automatic' => 'Automatique',
    'no_expiry_short' => 'Sans expiration',
    'copy_of' => ':name (copie)',

    'summary' => [
        'active' => 'Offres actives',
        'scheduled' => 'Planifiées',
        'expired' => 'Expirées',
        'redemptions' => 'Utilisations totales',
    ],

    'columns' => [
        'name' => 'Nom',
        'code' => 'Code',
        'type' => 'Type',
        'discount' => 'Remise',
        'applies' => 'S’applique à',
        'starts' => 'Début',
        'ends' => 'Fin',
        'used' => 'Utilisé',
        'status' => 'Statut',
    ],

    'types' => [
        'coupon' => 'Bon',
        'offer' => 'Offre',
    ],

    'statuses' => [
        'draft' => 'Brouillon',
        'scheduled' => 'Planifiée',
        'active' => 'Active',
        'expired' => 'Expirée',
        'disabled' => 'Désactivée',
    ],

    'applies' => [
        'booking' => 'Toute la réservation',
        'all_services' => 'Toutes les prestations',
        'services' => 'Prestations choisies',
        'categories' => 'Catégories choisies',
    ],

    'filters' => [
        'all_statuses' => 'Tous les statuts',
        'all_types' => 'Tous les types',
        'all_locations' => 'Tous les établissements',
        'reset' => 'Réinitialiser',
    ],

    'search' => 'Rechercher par nom, code ou prestation…',
    'results' => [
        'zero' => 'Aucun bon ni offre ne correspond',
        'one' => '1 bon ou offre',
        'many' => ':count bons et offres',
        'clear' => 'Effacer les filtres',
    ],
    'showing' => 'Affichage de :from à :to sur :total',
    'empty' => 'Rien ne correspond à ces filtres.',
    'actions_for' => 'Actions pour :name',

    /* -------------------------------------------------------------- le formulaire */

    'form' => [
        'create_title' => 'Créer un bon ou une offre',
        'edit_title' => 'Modifier le bon ou l’offre',

        'templates' => 'Partir d’un modèle',
        'templates_hint' => 'Un modèle ne fait que remplir le formulaire. Tout ce qu’il définit peut être changé avant d’enregistrer.',
        'scratch' => 'Partir de zéro',

        'basics' => 'Informations de base',
        'name' => 'Nom de l’offre',
        'name_placeholder' => 'Nouveau client −20 %',
        'description' => 'Description interne',
        'description_hint' => 'Pour l’équipe, pas pour le client.',
        'type' => 'Type d’offre',
        'type_coupon' => 'Code promo',
        'type_coupon_hint' => 'Le client ou l’accueil le saisit.',
        'type_offer' => 'Offre automatique',
        'type_offer_hint' => 'S’applique d’elle-même à toute réservation éligible.',
        'code' => 'Code promo',
        'code_hint' => 'Lettres, chiffres et tirets. Enregistré en majuscules.',
        'generate' => 'Générer',

        'discount' => 'Remise',
        'discount_type' => 'Type de remise',
        'percent' => 'Pourcentage',
        'fixed' => 'Montant fixe',
        'amount' => 'Montant',
        'percent_hint' => 'Un pourcentage de la part concernée. Jusqu’à 100.',
        'fixed_hint' => 'Une somme fixe en moins, jamais supérieure à la part concernée.',

        'applies' => 'S’applique à',
        'applies_hint' => 'Un pourcentage se retire de la part concernée, pas de la note entière.',
        'services' => 'Prestations',
        'categories' => 'Catégories',

        'locations' => 'Établissements',
        'all_locations' => 'Tous les établissements',
        'selected_locations' => 'Établissements choisis',

        'validity' => 'Validité',
        'starts' => 'Date de début',
        'ends' => 'Date de fin',
        'no_expiry' => 'Sans date d’expiration',
        'days' => 'Jours valides',
        'days_hint' => 'Laissez tous les jours cochés, sauf si l’offre vise des jours précis — un mardi vide ne se revend pas le mercredi.',

        'eligibility' => 'Pour qui',
        'eligibility_all' => 'Tous les clients',
        'eligibility_new' => 'Nouveaux clients uniquement',
        'eligibility_new_hint' => 'Personne n’a encore terminé de rendez-vous pour eux.',
        'eligibility_existing' => 'Clients existants uniquement',
        'eligibility_selected' => 'Clients choisis',
        'clients' => 'Clients',

        'redemption' => 'Règles d’utilisation',
        'min_spend' => 'Montant minimum de réservation',
        'min_spend_hint' => 'Facultatif. Empêche qu’une remise fixe soit utilisée sur une toute petite réservation.',
        'total_limit' => 'Utilisations totales',
        'total_limit_hint' => 'Laissez vide pour un nombre illimité.',
        'per_client_limit' => 'Par client',
        'per_client_limit_hint' => 'Laissez vide pour illimité. Une fois chacun est la réponse habituelle.',

        'availability' => 'Où elle peut être utilisée',
        'allow_online' => 'Autoriser les clients à l’utiliser lors de la réservation en ligne',
        'combinable' => 'Cumulable avec une autre offre',
        'combinable_hint' => 'Désactivé est la réponse prudente : une offre par réservation.',
        'draft' => 'Enregistrer comme brouillon',
        'draft_hint' => 'Un brouillon n’est proposé à personne tant que vous ne le désactivez pas.',

        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
    ],

    'weekdays' => [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ],

    /* ------------------------------------------------------------ les détails */

    'details' => [
        'title' => 'Détails de l’offre',
        'discount' => 'Remise',
        'for' => 'Disponible pour',
        'services' => 'Prestations',
        'locations' => 'Établissements',
        'valid' => 'Valide',
        'usage' => 'Utilisation',
        'online' => 'Réservation en ligne',
        'online_yes' => 'Les clients peuvent l’utiliser eux-mêmes',
        'online_no' => 'Personnel uniquement',
        'created_by' => 'Créée par :name',
        'no_expiry' => 'Sans expiration',
        'every_day' => 'Tous les jours',
    ],

    'report' => [
        'title' => 'Ce que cela donne',
        'redemptions' => 'Utilisations',
        'clients' => 'Clients',
        'discount' => 'Remise accordée',
        'revenue' => 'Chiffre d’affaires de ces réservations',
        'revenue_hint' => 'Ce qu’ont rapporté les réservations où elle a servi — pas une affirmation que l’offre les a provoquées.',
        'none' => 'Personne ne l’a encore utilisée.',
    ],

    'actions' => [
        'view' => 'Voir',
        'edit' => 'Modifier',
        'duplicate' => 'Dupliquer',
        'disable' => 'Désactiver',
        'enable' => 'Activer',
        'back' => 'Bons et offres',
    ],

    /* --------------------------------------------------------- la réponse */

    'refused' => [
        'not_running' => 'Cette offre n’est pas en cours.',
        'not_started' => 'Cette offre n’a pas encore commencé.',
        'expired' => 'Cette offre a expiré.',
        'wrong_day' => 'Cette offre ne s’applique pas ce jour-là.',
        'wrong_location' => 'Cette offre n’est pas disponible dans cet établissement.',
        'needs_a_client' => 'Cette offre ne vise que certains clients : la réservation doit en avoir un.',
        'new_only' => 'Cette offre est réservée aux nouveaux clients.',
        'existing_only' => 'Cette offre est réservée aux clients existants.',
        'not_for_this_client' => 'Cette offre n’est pas disponible pour ce client.',
        'no_eligible_services' => 'Rien dans cette réservation n’est éligible à cette offre.',
        'under_minimum' => 'Cette offre exige une réservation d’au moins :amount.',
        'fully_redeemed' => 'Cette offre a été entièrement utilisée.',
        'client_limit' => 'Ce client a déjà utilisé cette offre.',
        'unknown_code' => 'Aucune offre avec ce code.',
    ],

    'created' => 'Bon ou offre créé.',
    'saved' => 'Bon ou offre enregistré.',
    'duplicated' => 'Copié. Enregistré comme brouillon.',
    'disabled' => 'Offre désactivée.',
    'enabled' => 'Offre activée.',
];
