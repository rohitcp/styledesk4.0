<?php

declare(strict_types=1);

/*
| Le module Personnel : l'annuaire, la fiche et le formulaire d'ajout/modification.
|
| Les libellés de champs sont volontairement partagés par les trois. « Nom
| d'usage » désignant une chose sur une fiche et une autre sur le formulaire,
| c'est précisément la dérive qu'une clé unique existe pour empêcher.
*/

return [
    'title' => 'Membres de l’équipe',
    'summary' => '{1} :count membre actif|[2,*] :count membres actifs',
    'summary_pending' => '{1} :count invitation en attente|[2,*] :count invitations en attente',

    'add' => 'Ajouter un membre',
    'add_title' => 'Ajouter un membre',
    'add_intro' => 'Ses informations et son rôle. Les horaires, les disponibilités et les réglages par prestation se configurent une fois la fiche créée.',
    'adding' => 'Ajout en cours…',
    'save_and_add_another' => 'Enregistrer et en ajouter un autre',
    'edit' => 'Modifier',
    'edit_person' => 'Modifier :name',
    'edit_title' => 'Modifier le membre',
    'view_profile' => 'Voir la fiche',

    'created' => ':name a rejoint votre équipe.',
    'created_invited_to' => ':name a été ajouté et une invitation part vers :email.',
    'saved_person' => 'Les informations de :name ont été mises à jour.',
    'add_failed' => 'Impossible d’ajouter ce membre pour le moment. Réessayez.',
    'created_invited' => ':name a été ajouté et son invitation est en route.',
    'saved' => 'Membre mis à jour.',
    'deleted' => ':name a été retiré de votre équipe.',
    'save_failed' => 'Impossible d’enregistrer vos modifications. Vérifiez les informations et réessayez.',
    'correct_fields' => 'Corrigez les champs signalés et réessayez.',
    'delete_confirm' => 'Retirer :name de votre équipe ? Sa fiche est supprimée et, s’il avait un accès, il perd l’accès à cet établissement.',

    /* La grille de la liste — la même que servent les listes clients et
       prestations. */
    'view' => 'Voir le personnel',
    'manage_schedule' => 'Gérer le planning',
    'set_time_off' => 'Poser une absence',
    'activate' => 'Activer',
    'deactivate' => 'Désactiver',
    'deactivate_confirm' => 'Désactiver :name ? Sa fiche, ses prestations et ses salles sont conservées, mais il ne sera plus proposé à la réservation.',
    'activated_person' => ':name est de nouveau actif.',
    'deactivated_person' => ':name n’est plus actif.',
    'add_schedule' => 'Ajouter un planning',
    'results' => [
        'zero' => 'Aucun membre trouvé',
        'one' => '1 membre trouvé',
        'many' => ':count membres trouvés',
        'empty' => 'Aucun membre ne correspond à votre recherche ou à vos filtres.',
        'clear' => 'Effacer les filtres',
    ],
    'showing' => 'Affichage de :from à :to sur :total membres',
    'none_yet' => 'Aucun membre pour le moment',
    'none_yet_hint' => 'Ajoutez des membres pour gérer les plannings, les prestations, les établissements et les disponibilités.',

    'search_placeholder' => 'Rechercher par nom, e-mail, téléphone ou intitulé de poste',
    'search_label' => 'Rechercher un membre',
    'apply_filters' => 'Appliquer les filtres',

    'filters' => [
        'role' => 'Rôle',
        'all_roles' => 'Tous les rôles',
        'location' => 'Établissement',
        'all_locations' => 'Tous les établissements',
        'service' => 'Prestation',
        'all_services' => 'Toutes les prestations',
        'provider_type' => 'Type de praticien',
        'all_provider_types' => 'Tous les types de praticien',
        'employment' => 'Contrat',
        'all_employment' => 'Tous les types de contrat',
        'status' => 'Statut',
        'all_statuses' => 'Tous les statuts',
        'sort' => 'Trier par',
    ],

    'columns' => [
        'name' => 'Nom',
        'role' => 'Rôle',
        'job_title' => 'Intitulé de poste',
        'location' => 'Établissement',
        'contact' => 'Contact',
        'services' => 'Prestations',
        'status' => 'Statut',
        'last_login' => 'Dernière connexion',
        'actions' => 'Actions',
    ],

    'empty_title' => 'Aucun membre ne correspond à ces filtres.',
    'empty_hint' => 'Effacez les filtres, ou invitez quelqu’un depuis l’étape équipe.',
    'never' => 'Jamais',
    'all_locations' => 'Tous les établissements',
    'actions_for' => 'Actions pour :name',

    'cards' => [
        'shift_rule' => 'Règle de service',
        'shift_rule_hint' => 'Attribuez une règle de service réutilisable à ce membre. Elle servira à générer son planning.',
        'basic' => 'Informations de base',
        'contact' => 'Coordonnées',
        'employment' => 'Rôle et contrat',
        'employment_hint' => 'Le rôle décide de ce qu’il peut faire dans StyleDesk. Le type de contrat, c’est la façon dont l’établissement l’engage — les deux sont indépendants.',
        'account' => 'Compte',
    ],

    'fields' => [
        'shift_rule' => 'Règle de service',
        'no_shift_rule' => 'Aucune règle de service attribuée',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'middle_name' => 'Deuxième prénom',
        'preferred_name' => 'Nom d’usage',
        'preferred_name_placeholder' => 'Comment l’équipe et les clients l’appellent',
        'pronouns' => 'Pronoms',
        'job_title' => 'Intitulé de poste',
        'job_title_placeholder' => 'Coiffeur senior',
        'employee_ref' => 'Matricule',
        'employee_ref_hint' => 'Attribué automatiquement à l’enregistrement.',
        'avatar' => 'Photo de profil',
        'avatar_hint' => 'JPG, PNG ou WEBP, jusqu’à 2 Mo. Visible sur la page de réservation et dans la liste de l’équipe.',
        'bio' => 'Présentation',
        'bio_placeholder' => 'Affichée sur la page de réservation s’il prend des rendez-vous.',

        'email' => 'E-mail principal',
        'email_hint' => 'C’est aussi l’adresse à laquelle toute invitation est envoyée.',
        'work_email' => 'E-mail professionnel',
        'phone' => 'Téléphone principal',
        'phone_type' => 'Type de téléphone',
        'secondary_phone' => 'Téléphone secondaire',
        'emergency_contact_name' => 'Contact d’urgence',
        'emergency_contact_phone' => 'Téléphone d’urgence',
        'emergency_contact_relationship' => 'Lien',
        'relationship_placeholder' => 'Conjoint',
        'address' => 'Adresse',

        'role' => 'Rôle',
        'role_placeholder' => 'Choisissez un rôle',
        'role_hint' => 'Vous ne pouvez attribuer que des rôles compris dans votre propre accès.',
        'location' => 'Établissement principal',
        'employment_type' => 'Type de contrat',
        'provider_type' => 'Type de praticien',
        'specialities' => 'Spécialités',
        'services' => 'Prestations qu’il réalise',
        'services_hint' => 'Toute personne à qui des prestations sont attribuées devient réservable nommément.',
        'services_placeholder' => 'Rechercher ou sélectionner des prestations',
        'resources' => 'Ressources qu’il utilise',
        'resources_hint' => 'Les fauteuils, salles ou postes utilisés par cette personne. Laissez vide si n’importe lequel convient.',
        'resources_placeholder' => 'Rechercher ou sélectionner des ressources',
        'date_of_birth' => 'Date de naissance',
        'started_on' => 'Date d’entrée',

        'account_status' => 'Statut du compte',
        'account_status_hint' => 'Un membre inactif ne peut pas être réservé et ne reçoit aucun nouveau rendez-vous.',
        'login_enabled' => 'Autoriser la connexion',
        'login_enabled_hint' => 'Il obtient son propre compte StyleDesk. Laissez désactivé pour quelqu’un qui doit seulement apparaître dans l’agenda.',
        'send_invitation' => 'Envoyer l’invitation maintenant',
        'send_invitation_hint' => 'Lui envoie par e-mail un lien pour définir un mot de passe et rejoindre l’équipe. Vous pouvez aussi l’envoyer plus tard.',
        'invitation_message' => 'Message',
        'invitation_message_placeholder' => 'Au plaisir de vous compter dans l’équipe.',
    ],

    'not_specified' => 'Non renseigné',

    'validation' => [
        'shift_rule_unavailable' => 'Cette règle de service n’est pas disponible pour l’établissement choisi. Sélectionnez-en une autre.',
        'first_name_required' => 'Le prénom est obligatoire.',
        'last_name_required' => 'Le nom est obligatoire.',
        'email_required' => 'L’e-mail principal est obligatoire.',
        'email_invalid' => 'Saisissez une adresse e-mail valide.',
        'email_taken' => 'Quelqu’un de votre équipe utilise déjà cette adresse e-mail.',
        'role_required' => 'Choisissez un rôle pour cette personne.',
        'role_invalid' => 'Vous ne pouvez attribuer que des rôles compris dans votre propre accès.',
        'location_invalid' => 'Choisissez l’un de vos propres établissements.',
        'service_invalid' => 'Choisissez l’une de vos propres prestations.',
        'role_not_yours' => 'Vous ne pouvez pas attribuer ce rôle.',
        'own_role' => 'Vous ne pouvez pas changer votre propre rôle. Demandez à un autre administrateur.',
        'avatar_max' => 'La photo de profil doit faire 2 Mo ou moins.',
        'avatar_mimes' => 'Utilisez une image JPG, PNG ou WEBP.',
    ],

    /*
     * Les libellés des informations sur la fiche.
     *
     * Séparés de `fields` parce que la fiche énonce ce qu'est une valeur
     * (« Nom légal ») là où le formulaire en demande les parties
     * (« Prénom ») ; les confondre ferait poser à un écran la question que
     * l'autre est en train de répondre.
     */
    'profile' => [
        'about' => 'À propos',
        'legal_name' => 'Nom légal',
        'preferred_name' => 'Nom d’usage',
        'pronouns' => 'Pronoms',
        'employee_ref' => 'Matricule',

        'contact' => 'Contact',
        'email' => 'E-mail principal',
        'work_email' => 'E-mail professionnel',
        'phone' => 'Téléphone principal',
        'secondary_phone' => 'Téléphone secondaire',
        'address' => 'Adresse',
        'emergency_contact' => 'Contact d’urgence',

        'access' => 'Rôle et accès',
        'role' => 'Rôle',
        'location' => 'Établissement principal',
        'login' => 'Connexion',
        'account' => 'Compte',
        'last_login' => 'Dernière connexion',

        'services' => 'Prestations',
        'assign_services' => 'Attribuer des prestations',

        'employment' => 'Contrat',
        'employment_type' => 'Type de contrat',
        'provider_type' => 'Type de praticien',
        'specialities' => 'Spécialités',
        'added' => 'Ajouté le',

        'invitation' => 'Invitation',
        'sent_to' => 'Envoyée à',

        'activity' => 'Activité',
        'activity_hint' => 'Les modifications administratives de cette fiche.',
        'activity_empty' => 'Rien d’enregistré pour le moment.',

        'bookable' => 'Réservable',
        'no_login' => 'Sans accès',
        'summary_email' => 'E-mail',
        'summary_phone' => 'Téléphone',
        'summary_location' => 'Établissement',
    ],

    'edit_staff' => 'Modifier le membre',
    'more_actions' => 'Plus',
    'set_on_leave' => 'Mettre en congé',
    'on_leave_person' => ':name est en congé.',
    'services_added' => '{1} :count prestation ajoutée.|[2,*] :count prestations ajoutées.',
    'service_removed' => ':name retirée. La prestation elle-même n’est pas touchée.',
    'shift_rule_assigned' => 'Appliquée à :name.',
    'shift_rule_cleared' => 'Règle de service retirée.',

    'tabs' => [
        'overview' => 'Vue d’ensemble',
        'schedule' => 'Planning',
        'services' => 'Prestations',
        'notes' => 'Notes',
    ],

    /* Le rapport de l'onglet Vue d'ensemble. Uniquement ce que les données
       permettent aujourd'hui — les chiffres de rendez-vous demandent un module
       de réservation, et une carte affichant « — » apprend au lecteur à
       ignorer la ligne. */
    'report' => [
        'shifts_this_week' => 'Services cette semaine',
        'hours_this_week' => 'Heures cette semaine',
        'shifts_this_month' => 'Services ce mois-ci',
        'hours_this_month' => 'Heures ce mois-ci',
        'upcoming_shifts' => 'Services à venir',
        'services' => 'Prestations',
    ],

    'schedule_tab' => [
        'shift_rule' => 'Règle de service',
        'current_rule' => 'Règle de service actuelle',
        'remove_rule' => 'Retirer la règle de service',
        'remove_rule_confirm' => 'Retirer :rule de :name ? Les services déjà à son planning restent tels quels ; seul le motif derrière eux est retiré.',
        'no_rule' => 'Aucune règle de service attribuée.',
        'assign_rule' => 'Attribuer une règle de service',
        'change_rule' => 'Changer de règle de service',
        'working_schedule' => 'Planning de travail',
        'weeks' => '{1} 1 semaine|[2,*] :count semaines',
        'previous' => 'Précédent',
        'today' => 'Aujourd’hui',
        'next' => 'Suivant',
        'not_working' => 'Ne travaille pas',
    ],

    'services_tab' => [
        'title' => 'Prestations qu’il peut réaliser',
        'intro' => 'Ce pour quoi cette personne peut être réservée. En retirer une lui ôte la possibilité d’être réservée pour cela — la prestation elle-même n’est pas touchée.',
        'add' => 'Ajouter des prestations',
        'choose' => 'Prestations à ajouter',
        'remove' => 'Retirer',
        'remove_confirm' => 'Retirer :service de :name ? Il ne sera plus réservable pour cette prestation. La prestation elle-même n’est pas touchée.',
        'none' => 'Aucune prestation attribuée',
        'none_hint' => ':name ne peut pas être réservé nommément tant qu’aucune prestation ne lui est attribuée.',
    ],

    'notes' => [
        'title' => 'Notes internes',
        'intro' => 'Les notes que l’établissement conserve sur cette personne — planning, formation, disponibilité. Jamais montrées à un client ni sur les pages de réservation.',
        'body' => 'Note',
        'placeholder' => 'Tout ce que l’équipe devrait savoir.',
        'add' => 'Ajouter une note',
        'added' => 'Note ajoutée.',
        'deleted' => 'Note supprimée.',
        'delete_confirm' => 'Supprimer cette note ? Elle ne pourra pas être récupérée.',
        'body_required' => 'Écrivez quelque chose avant d’ajouter la note.',
        'none' => 'Aucune note pour le moment.',
        'someone' => 'Quelqu’un',
    ],

    /*
    | Taux d'occupation — quelle part de la journée réservable de chacun est
    | réservée.
    |
    | Le vocabulaire est celui d'un responsable, pas d'un rapport : « 3 % sous
    | l'objectif » plutôt que « écart -3 », et « non planifié » plutôt que
    | « 0 % ». Quelqu'un qui n'était pas au planning n'avait pas de journée à
    | remplir.
    */
    'utilization' => [
        'title' => 'Taux d’occupation',
        'subtitle' => 'Quelle part du temps réservable de votre équipe est réservée',
        'intro' => 'Le taux d’occupation se mesure sur les heures où chacun est réellement au planning pour recevoir des clients — le temps planifié, moins les pauses et le temps non réservable.',

        'period' => 'Période',
        'loading' => 'Mise à jour…',
        'apply' => 'Appliquer',
        'cancel' => 'Annuler',
        'clear' => 'Effacer',
        'search' => 'Rechercher un membre…',
        'all_locations' => 'Tous les établissements',
        'all_roles' => 'Tous les rôles',
        'all_statuses' => 'Tous les statuts',
        'filters_active' => 'Filtres',

        'average' => 'Taux d’occupation moyen',
        'average_for' => 'Moyenne de l’équipe',
        'target' => 'Objectif : :target %',
        'target_met' => 'Objectif atteint',
        'above_target' => ':count % au-dessus de l’objectif',
        'below_target' => ':count % sous l’objectif',
        'on_target' => 'Dans l’objectif',

        'booked_line' => ':hours h réservées',
        'available_line' => 'sur :hours h réservables',
        'unused_line' => ':hours h inutilisées',
        'scheduled_count' => '{1} 1 personne planifiée|[2,*] :count personnes planifiées',
        'bookings_count' => '{0} Aucune réservation|{1} 1 réservation|[2,*] :count réservations',
        'used_of_short' => ':used h réservées sur :available',

        'summary' => [
            'scheduled' => 'Personnel planifié',
            'bookable' => 'Heures réservables',
            'booked' => 'Heures réservées',
            'unused' => 'Heures inutilisées',
            'on_target' => 'À l’objectif ou au-dessus',
            'under' => 'Sous-occupé',
        ],

        'statuses' => [
            'high' => 'Occupation élevée',
            'on_target' => 'Objectif atteint',
            'near_target' => 'Proche de l’objectif',
            'low' => 'Occupation faible',
            'very_low' => 'Très faible',
            'unscheduled' => 'Non planifié',
        ],

        'timeline' => [
            'title' => 'La journée de l’équipe',
            'legend' => [
                'booked' => 'Réservé',
                'available' => 'Disponible',
                'break' => 'Pause',
                'blocked' => 'Non réservable',
                'off' => 'Hors horaires de travail',
            ],
        ],

        'detail' => [
            'utilization' => 'Occupation',
            'target' => 'Objectif',
            'bookable' => 'Réservable',
            'booked' => 'Réservé',
            'unused' => 'Inutilisé',
            'bookings' => 'Réservations',
            'revenue' => 'Chiffre d’affaires',
            'average_value' => 'Réservation moyenne',
            'vs_team' => 'vs moyenne de l’équipe',
            'vs_target' => 'vs objectif',
            'day' => 'La journée',
            'daily' => 'Jour par jour',
            'services' => 'Ce qu’il a fait',
            'gaps' => 'Capacité disponible',
            'gaps_hint' => 'Les trous assez longs pour être vendus, et ce qui y entre.',
            'gaps_none' => 'Aucun trou vendable dans cette journée.',
            'no_bookings' => 'Aucune réservation sur cette période.',
            'minutes' => ':count min',
            'close' => 'Fermer',
            'open_staff' => 'Ouvrir la fiche',
        ],

        'comparison' => 'Comparaison de l’équipe',

        'table' => [
            'staff' => 'Membre',
            'location' => 'Établissement',
            'scheduled' => 'Planifié',
            'bookable' => 'Réservable',
            'booked' => 'Réservé',
            'idle' => 'Inutilisé',
            'bookings' => 'Réservations',
            'utilization' => 'Occupation',
            'target' => 'Objectif',
            'variance' => 'Écart',
            'revenue' => 'Chiffre d’affaires',
            'status' => 'Statut',
        ],

        'empty' => 'Aucun planning disponible',
        'empty_hint' => 'Le taux d’occupation a besoin d’horaires de travail publiés pour être calculé.',
        'empty_cta' => 'Voir le planning',
        'no_matches' => 'Personne ne correspond à ces filtres.',
    ],
];
