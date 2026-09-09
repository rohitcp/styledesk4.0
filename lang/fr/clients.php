<?php

declare(strict_types=1);

/*
| Réglages → Clients.
|
| Les libellés du catalogue lui-même — noms de champs, statuts, panneaux —
| vivent dans config/clients.php à côté des valeurs qu'ils décrivent, et ne
| remontent par ce fichier que là où une langue a besoin de ses propres mots.
*/

return [
    'title' => 'Clients',
    'intro' => 'Ce que contient une fiche client, comment elle se comporte, et ce que votre équipe voit en l’ouvrant.',
    'saved' => 'Réglages clients mis à jour.',
    'scope_note' => 'Ce sont des réglages globaux. Les fiches clients, l’historique et la gestion au quotidien relèvent du module Clients.',
    'pending_note' => 'Les réglages qui décrivent les écrans de réservation et les fiches clients sont enregistrés dès maintenant et prendront effet à l’arrivée de ces écrans.',

    'sections' => 'Sections de cette page',
    /*
    | Ce que les propres réservations d'un client disent de lui, sous forme de
    | phrases qu'une personne peut prononcer. Déduit de l'agenda — voir
    | App\Support\ClientInsights.
    */
    'insights' => [
        'cadence' => '{1} Réserve en général toutes les semaines|[2,*] Réserve en général toutes les :count semaines',
        'service' => 'Réserve le plus souvent :service',
        'staff' => 'Réserve en général avec :name',
        'value' => 'Panier moyen :amount',
        'window' => 'Préfère les rendez-vous :window',
        'attendance' => ':count dernières visites honorées, aucune annulation',
    ],

    'behavioral' => [
        'title' => 'Étiquettes comportementales',
        'intro' => 'Des étiquettes que StyleDesk déduit lui-même des réservations, de la présence, des dépenses et de l’engagement — par opposition à celles que votre équipe pose à la main.',
        'pending' => 'Elles se comptent à partir des réservations, de la présence et des dépenses. Rien n’est appliqué à un client tant que ces parties de StyleDesk ne sont pas là ; ce que vous choisissez ici, c’est lesquelles le seront.',
        'note' => 'Les étiquettes comportementales sont définies par StyleDesk et ne peuvent être ni renommées ni supprimées : leurs noms sont ce à quoi les rapports et l’automatisation future se réfèrent. En désactiver une arrête son application et la laisse sur les clients qui la portent déjà.',
        'updated' => 'Étiquettes comportementales mises à jour',
        'search' => 'Rechercher une étiquette comportementale…',
        'no_matches' => 'Aucune étiquette ne correspond.',
        'none_yet' => 'Aucune pour l’instant. Elles se déduisent des réservations, de la présence et des dépenses à mesure qu’elles arrivent.',
        'manage' => 'Gérer les étiquettes comportementales',
        'other' => 'Autre',
        'activated' => '« :label » sera appliquée',
        'deactivated' => '« :label » ne sera plus appliquée',
    ],

    'expand_all' => 'Tout déplier',
    'collapse_all' => 'Tout replier',
    'unsaved_warning' => 'Cette section contient des modifications non enregistrées. Les abandonner ?',
    'preview' => [
        'fields' => 'Champs d’une fiche client',
        'required' => '(obligatoire)',
        'optional' => '(facultatif)',
        'more' => '+:count de plus',
    ],

    'summary' => [
        'fields' => '{1} 1 champ sur une fiche client|[2,*] :count champs sur une fiche client',
        'preferences' => '{0} Aucune préférence|{1} 1 préférence|[2,*] :count préférences',
        'tags' => '{0} aucune étiquette|{1} 1 étiquette|[2,*] :count étiquettes',
        'behavioral' => '{0} Aucune des :total étiquettes comportementales appliquée|[1,*] :count des :total étiquettes comportementales appliquées',
        'notes_on' => '{0} Notes activées, une par client|[1,*] Notes activées, plusieurs par client',
        'notes_off' => 'Notes désactivées',
        'panels' => '{0} Rien d’affiché pendant la réservation|{1} 1 panneau pendant la réservation|[2,*] :count panneaux pendant la réservation',
        'duplicates_off' => 'Aucune alerte de doublon',
        'status' => 'Clients inactifs réservables : :inactive · Archivés dans la recherche : :archived',
        'consent_on' => 'Consentement enregistré lorsqu’il est donné',
        'consent_off' => 'Consentement non enregistré',
    ],

    'cards' => [
        'records' => 'Fiches clients',
        'records_hint' => 'Champs de la fiche, ce qui est obligatoire, l’écriture des noms, et l’identifiant client.',
        'lists' => 'Préférences et étiquettes',
        'lists_hint' => 'Les préférences structurées et les libellés que votre équipe peut poser sur un client.',
        'notes' => 'Notes',
        'notes_hint' => 'Les notes clients, qui peut les écrire et où elles apparaissent.',
        'booking' => 'Comportement à la réservation',
        'booking_hint' => 'Ce que l’écran de réservation montre du client sélectionné.',
        'duplicates' => 'Détection des doublons',
        'duplicates_hint' => 'Comment un doublon possible est repéré, et ce qui se passe alors.',
        'communication' => 'Communication',
        'communication_hint' => 'Les moyens de contacter un client, et ce à quoi il peut consentir.',
        'status' => 'Statut et archivage',
        'status_hint' => 'Clients actifs, inactifs et archivés, et où chacun apparaît.',
        'privacy' => 'Confidentialité et consentement',
        'privacy_hint' => 'Ce qui est enregistré lorsqu’un client donne ou retire son consentement marketing.',
    ],

    'defaults' => [
        'title' => 'Valeurs par défaut d’un nouveau client',
        'status' => 'Statut',
        'location' => 'Établissement préféré',
        'staff' => 'Praticien préféré',
        'communication' => 'Moyen de communication',
        'marketing' => 'Consentement marketing',
        'none' => 'Aucun',
    ],

    'fields' => [
        'title' => 'Champs de la fiche',
        'hint' => 'Quels champs porte une fiche client, et lesquels doivent être remplis. Faites glisser pour réordonner.',
        'field' => 'Champ',
        'enabled' => 'Activé',
        'required' => 'Obligatoire',
        'locked' => 'Toujours activé',
        'locked_hint' => 'Une fiche client sans nom n’est utilisable par personne.',
        'required_disabled' => 'Un champ désactivé ne peut pas être obligatoire.',
        'contact_advice' => 'C’est un numéro de mobile ou une adresse e-mail qui rend un doublon repérable. Exiger au moins l’un des deux mérite réflexion.',
    ],

    'name_format' => 'Comment les noms de clients s’écrivent',
    'name_format_hint' => 'Utilisé partout où un client est nommé, pour qu’un même client ne se lise pas comme deux personnes sur deux écrans.',
    'name_preview' => 'Par exemple',

    'client_id' => 'Identifiant client',
    'client_id_hint' => 'Attribué automatiquement, unique à votre établissement, et recherchable. Il ne peut pas être modifié.',
    'client_id_example' => 'Par exemple :example',

    'preferences' => [
        'title' => 'Préférences client',
        'enabled' => 'Activer les préférences client',
        'multiple' => 'Autoriser plusieurs préférences par client',
        'add' => 'Ajouter une préférence',
        'placeholder' => 'Cuir chevelu sensible',
        'empty' => 'Aucune préférence pour le moment.',
        'deactivate_note' => 'La désactiver la laisse sur les clients qui l’ont déjà ; elle cesse simplement d’être proposée.',
    ],

    'tags' => [
        'name' => 'Nom de l’étiquette',
        'deleted' => 'Étiquette supprimée',
        'delete_confirm' => 'Supprimer l’étiquette « :label » ? Aucun client ne l’utilise.',
        'in_use' => '« :label » est sur :count clients, elle ne peut donc pas être supprimée. Désactivez-la plutôt — les clients la gardent et plus personne ne peut la choisir.',
        'title' => 'Étiquettes client',
        'enabled' => 'Activer les étiquettes client',
        'add' => 'Ajouter une étiquette',
        'placeholder' => 'VIP',
        'colour' => 'Couleur',
        'empty' => 'Aucune étiquette pour le moment.',
        'multiple_note' => 'Un client peut porter plusieurs étiquettes.',
    ],

    'notes' => [
        'enabled' => 'Activer les notes client',
        'multiple' => 'Autoriser plusieurs notes par client',
        'in_booking' => 'Afficher les notes pendant la réservation',
        'important_on_profile' => 'Afficher les notes importantes en haut de la fiche',
        'allow_important' => 'Permettre au personnel de marquer une note comme importante',
        'staff_can_edit' => 'Permettre au personnel de modifier ses propres notes',
        'admin_can_delete' => 'Permettre au propriétaire et aux administrateurs de supprimer des notes',
    ],

    'duplicates' => [
        'rules' => 'Considérer un client comme un doublon possible lorsqu’il partage',
        'warning' => 'Avertir avant de créer un doublon possible',
        'show_matches' => 'Montrer les clients correspondants, pour pouvoir ouvrir celui qui existe déjà',
        'no_merge' => 'Les fiches ne sont jamais fusionnées automatiquement. Deux personnes peuvent partager un téléphone, et un historique fusionné à tort n’est pas quelque chose qu’un accueil peut démêler.',
    ],

    'search' => 'Trouver un client par',
    'creation' => 'Un client peut être créé depuis',
    'creation_unavailable' => 'Pas encore disponible',
    'booking_panels' => 'Afficher sur l’écran de réservation',
    'history_panels' => 'Afficher sur la fiche client',

    'status' => [
        'allow_booking_inactive' => 'Autoriser la réservation pour les clients inactifs',
        'archived_in_search' => 'Inclure les clients archivés dans la recherche de réservation',
        'explainer' => 'Les clients actifs apparaissent normalement. Les clients inactifs apparaissent avec une pastille. Les clients archivés restent hors de la recherche de réservation mais conservent leurs rendez-vous, transactions et historique.',
    ],

    'communication' => [
        'methods' => 'Moyens de contacter un client',
        'marketing' => 'Marketing auquel un client peut consentir',
        'stored_separately' => 'Chacun est enregistré séparément : un client qui accepte les rappels n’a pas accepté le marketing.',
    ],

    'consent' => [
        'record' => 'Enregistrer le consentement marketing',
        'record_date' => 'Enregistrer la date à laquelle il a été donné',
        'record_captured_by' => 'Enregistrer qui l’a recueilli',
        'client_can_opt_out' => 'Permettre aux clients de se désinscrire',
        'show_on_profile' => 'Afficher le statut du consentement sur la fiche client',
        'later' => 'Les formulaires de consentement numériques, le consentement aux soins et aux photos, les mentions de confidentialité et les signatures électroniques arriveront dans une version ultérieure.',
    ],

    'deactivate' => 'Désactiver',
    'activate' => 'Activer',

    'preference_added' => 'Préférence ajoutée.',
    'preference_activated' => 'Préférence activée.',
    'preference_deactivated' => 'Préférence désactivée. Les clients qui l’ont déjà la conservent.',
    'tag_added' => 'Étiquette ajoutée.',
    'tag_saved' => 'Étiquette mise à jour.',
    'tag_activated' => 'Étiquette activée.',
    'tag_deactivated' => 'Étiquette désactivée. Les clients qui l’ont déjà la conservent.',
    'order_saved' => 'Ordre enregistré.',

    'validation' => [
        'status_required' => 'Choisissez un statut par défaut.',
        'name_format_required' => 'Choisissez comment les noms de clients s’écrivent.',
        'location_invalid' => 'Choisissez l’un de vos propres établissements.',
        'staff_invalid' => 'Choisissez l’un de vos propres praticiens.',
        'preference_exists' => 'Vous avez déjà une préférence portant ce nom.',
        'tag_exists' => 'Vous avez déjà une étiquette portant ce nom.',
    ],

    /*
     * La page d'accueil du module Clients — pas l'écran de réglages.
     *
     * Gardée dans ce fichier parce que les deux parlent de la même chose, et
     * que le lecteur de l'un est le lecteur de l'autre.
     */
    /*
     * Le module Clients : la liste, l'état vide, le formulaire et la fiche.
     * Ses réglages sont les clés ci-dessus.
     */
    'module' => [
        'title' => 'Clients',
        'intro' => 'Gérez les fiches clients, les préférences, les coordonnées, les notes et l’historique des réservations.',

        'add' => 'Ajouter un client',
        'add_another' => 'Enregistrer et en ajouter un autre',
        'add_first' => 'Ajoutez votre premier client',
        'add_title' => 'Ajouter un client',
        'edit_title' => 'Modifier le client',
        'view' => 'Voir le client',
        'create_booking' => 'Créer une réservation',
        'archive' => 'Archiver le client',
        'restore' => 'Restaurer le client',

        'choose_birth_date' => 'Choisissez une date de naissance',
        'created' => 'Client ajouté',
        'saved' => 'Client mis à jour.',
        'archived' => ':name a été archivé. Ses rendez-vous et son historique sont inchangés.',
        'restored' => ':name est de nouveau actif.',
        'correct_fields' => 'Corrigez les champs signalés et réessayez.',

        /*
         * L'état vide, selon §État vide — volontairement pas un tableau vide.
         * Recherche, filtres, pagination et « 0 résultat » sont tous absents
         * tant qu'un établissement n'a pas au moins un client : un champ de
         * recherche portant sur rien est un contrôle qui ne peut qu'échouer.
         */
        'empty_title' => 'Commencez à constituer votre fichier client',
        'empty_body' => 'Ajoutez vos clients pour garder au même endroit leurs coordonnées, préférences, historique de réservation, notes et praticien préféré.',
        'video_title' => 'Comment fonctionnent les clients dans StyleDesk',
        'video_body' => 'Ce que fait le module Clients, comment ajouter quelqu’un, et comment ses préférences, son praticien préféré, ses notes et son historique servent à la prochaine réservation.',
        'video_pending' => 'La vidéo explicative arrive bientôt.',

        'search_placeholder' => 'Rechercher un client',
        'filters' => [
            'location_short' => 'Établissement',
            'staff_short' => 'Équipe',
            'tag_short' => 'Étiquette',
            'active' => 'Filtres actifs',
            'status' => 'Statut',
            'all_statuses' => 'Tous les statuts',
            'location' => 'Établissement préféré',
            'all_locations' => 'Tous les établissements',
            'staff' => 'Praticien préféré',
            'all_staff' => 'Tous les praticiens',
            'tag' => 'Étiquette',
            'all_tags' => 'Toutes les étiquettes',
        ],

        'columns' => [
            'client' => 'Client',
            'mobile' => 'Mobile',
            'email' => 'E-mail',
            'staff' => 'Praticien préféré',
            'location' => 'Établissement préféré',
            'last_visit' => 'Dernière visite',
            'next_booking' => 'Prochaine réservation',
            'status' => 'Statut',
            'actions' => 'Actions',
        ],

        'never_visited' => 'Aucune visite',
        'nothing_booked' => 'Rien de réservé',
        'all_archived' => 'Tous les clients sont archivés.',
        'all_archived_hint' => 'Les clients archivés restent hors de cette liste et de la recherche de réservation, et conservent tout ce qui figure déjà sur leur fiche.',
        'view_archived' => 'Voir les clients archivés',
        'stats' => [
            'total' => 'Total clients',
            'new_this_month' => 'Nouveaux ce mois-ci',
            'upcoming' => 'Réservations à venir',
            'inactive' => 'Clients inactifs',
            'total_note' => 'Toutes les fiches clients',
            'inactive_note' => 'Ne réservent pas en ce moment',
            'vs_last_month' => 'vs mois dernier',
            'awaiting_bookings' => 'Dès l’arrivée des réservations',
        ],
        'results' => [
            'zero' => 'Aucun client trouvé',
            'one' => '1 client trouvé',
            'many' => ':count clients trouvés',
            'empty' => 'Aucun client ne correspond à votre recherche ou à vos filtres.',
            'clear' => 'Effacer les filtres',
        ],
        'showing' => 'Affichage de :from à :to sur :total clients',
        'no_matches' => 'Aucun client ne correspond à votre recherche.',
        'no_matches_hint' => 'Essayez un autre mot, ou effacez les filtres.',
        'actions_for' => 'Actions pour :name',

        'archive_confirm' => 'Archiver :name ? Il reste hors de la recherche de réservation et conserve chaque rendez-vous, transaction et note déjà sur sa fiche.',
        'restore_confirm' => 'Rendre :name de nouveau actif ? Il réapparaîtra dans la recherche de réservation.',

        /*
         * Doublons possibles, selon §7. Un avertissement qui nomme ce qu'il a
         * trouvé — « ceci est peut-être un doublon » sans rien à regarder ne
         * laisse à l'accueil aucun moyen de décider.
         */
        'duplicates_title' => 'Vous avez peut-être déjà cette personne',
        'duplicates_body' => 'Ces clients partagent une information avec celui que vous ajoutez. Ouvrez-en un pour vérifier, ou confirmez qu’il s’agit d’une autre personne.',
        'duplicates_confirm' => 'Ajouter quand même — c’est une autre personne',

        'cards' => [
            'about' => 'À propos',
            'contact' => 'Coordonnées',
            'booking' => 'Réservation',
            'preferences' => 'Préférences et étiquettes',
            'communication' => 'Communication et consentement',
            'notes' => 'Notes',
        ],

        'consent_given' => 'Donné le :when',
        'consent_by' => 'Recueilli par :name',
        'no_consent' => 'Aucun consentement marketing enregistré',
        'no_preferences' => 'Rien d’enregistré',

        /**
         * Les numéros de téléphone et adresses e-mail d'une fiche client.
         *
         * « Principal » nomme ce qu'est une ligne plutôt que ce que fait un
         * clic dessus, parce que le contrôle à côté est un bouton radio : le
         * lecteur choisit parmi les numéros, il n'agit pas sur l'un d'eux.
         */
        'contacts' => [
            'primary' => 'Principal',
            'secondary' => 'Secondaire',
            'priority' => 'Priorité',
            'add_phone' => 'Ajouter un autre numéro',
            'add_email' => 'Ajouter une autre adresse',
            'remove' => 'Supprimer',
            'remove_phone_title' => 'Retirer ce numéro ?',
            'remove_phone_body' => ':contact sera retiré de ce client à l’enregistrement.',
            'remove_email_title' => 'Retirer cette adresse ?',
            'remove_email_body' => ':contact sera retirée de ce client à l’enregistrement.',
            'phone_number' => 'Numéro de téléphone',
            'email_address' => 'Adresse e-mail',
            'phone_type' => 'Type de téléphone',
            'email_type' => 'Type d’adresse',
            'country_code' => 'Indicatif du pays',
            'other_numbers' => 'Autres numéros',
            'other_emails' => 'Autres adresses',
        ],

        /**
         * L'espace de travail client.
         *
         * Les états vides disent ce qui est vrai aujourd'hui plutôt que
         * « aucune donnée » : un client sans rendez-vous n'en a pas parce que
         * les réservations ne sont pas encore livrées, et une page qui le dit
         * n'oblige personne à enquêter.
         */
        'booking_preferences' => [
            'title' => 'Préférences de réservation',
            'none' => 'Rien de noté pour l’instant.',
            'add' => 'Ajouter une préférence de réservation',
            'placeholder' => 'Préfère les rendez-vous l’après-midi',
            'remove' => 'Retirer « :label »',
            'added' => 'Préférence de réservation ajoutée.',
            'removed' => 'Préférence de réservation retirée.',
        ],
        'workspace' => [
            'back' => 'Retour aux clients',
            'dob' => 'Né(e) le : :date',
            'client_since' => 'Client depuis le :date',
            'summary' => [
                'last_visit' => 'Dernière visite',
                /* Renommé : ce qu'un accueil y lit. « Prochaine réservation »
                   est la ligne d'une liste ; « Prochain rendez-vous » est ce
                   pour quoi le client vient réellement. */
                'next_appointment' => 'Prochain rendez-vous',
                'total_visits' => 'Total des visites',
                'lifetime_spend' => 'Dépenses cumulées',
                /* Dit avec des mots plutôt que laissé en tiret : « Aucune
                   visite précédente » est un fait sur ce client, un blanc est
                   une carte qui semble cassée. */
                'no_visits' => 'Aucune visite précédente',
                'no_upcoming' => 'Aucun rendez-vous à venir',
                'no_visits_yet' => 'Aucune visite terminée pour l’instant',
                'nothing_paid' => 'Rien de payé pour l’instant',
            ],
            'tabs' => [
                'rewards' => 'Récompenses',
                'membership' => 'Abonnement',
                'leads' => 'Demandes',
                'activity' => 'Activité',
                'bookings' => 'Réservations',
                'services' => 'Prestations',
                'notes' => 'Notes',
                'files' => 'Fichiers',
            ],
            'services' => [
                'favorites' => 'Prestations favorites',
                'no_favorites' => 'Aucune prestation favorite pour l’instant.',
                'favorites_hint' => 'Marquez les prestations que ce client prend habituellement, pour que la prochaine réservation soit un clic plutôt qu’une recherche.',
                'add_favorite' => '+ Ajouter une prestation favorite',
                'remove_favorite' => 'Retirer :name des favorites',
                'choose_services' => 'Choisir des prestations',
                'search_services' => 'Rechercher une prestation…',
                'history' => 'Historique des prestations',
                'no_history' => 'Rien de réservé pour l’instant. Cela se remplit depuis ses rendez-vous.',
                'visits' => ':count visites',
                'visits_one' => '1 visite',
                'add_to_favorites' => 'Ajouter aux favorites',
                'is_favorite' => 'Favorite',
                'columns' => [
                    'service' => 'Prestation',
                    'category' => 'Catégorie',
                    'visits' => 'Visites',
                    'last_booked' => 'Dernière réservation',
                    'last_provider' => 'Dernier praticien',
                    'favorite' => 'Favorite',
                ],
            ],
            'quick' => [
                'create_booking' => 'Créer une réservation',
                'add_note' => 'Ajouter une note',
                'send_message' => 'Envoyer un message',
            ],
            'identity' => [
                'preferred_name' => 'Se fait appeler :name',
                'edit_client' => 'Modifier le client',
                'more_actions' => 'Plus d’actions',
                'mark_inactive' => 'Marquer inactif',
                'mark_active' => 'Marquer actif',
            ],
            'contact' => [
                'title' => 'Coordonnées',
                'phone' => 'Téléphone',
                'email' => 'E-mail',
                'address' => 'Adresse',
                'view_all_numbers' => 'Voir les :count numéros',
                'view_all_emails' => 'Voir les :count adresses',
                'copy' => 'Copier',
                'copied' => 'Copié',
                'none_recorded' => 'Rien d’enregistré',
                'hidden' => 'Vous n’avez pas accès aux coordonnées de ce client.',
                'email_short' => 'E-mail',
                'sms_short' => 'SMS',
                'no_email' => 'Aucune adresse e-mail au dossier',
                'no_phone' => 'Aucun numéro de téléphone au dossier',
                'send_email' => 'Envoyer un e-mail',
                'send_sms' => 'Envoyer un SMS',
                'call' => 'Appeler',
            ],
            'preferences' => [
                'contact_title' => 'Préférences de contact',
                'contactable' => 'Joignable par',
                'marketing' => 'Marketing',
                'opted_in' => 'A consenti',
                'opted_out' => 'A refusé',
                'title' => 'Préférences',
                'view_all' => 'Voir les :count préférences',
                'none' => 'Aucune préférence enregistrée pour l’instant.',
            ],
            'preferred' => [
                'title' => 'Préférences',
                'staff' => 'Praticien',
                'location' => 'Établissement',
                'none' => 'Aucune préférence',
            ],
            'important' => [
                'title' => 'Note privée',
                'none' => 'Rien de signalé comme important.',
            ],
            'profile_note' => 'Note de la fiche',
            'tags' => [
                'title' => 'Étiquettes client',
                'intro' => 'Organisez vos clients avec des étiquettes, pour la réservation, les prestations, le marketing et le suivi de la relation.',
                'none' => 'Aucune étiquette client attribuée',
                'manage' => 'Gérer les étiquettes client',
                'search' => 'Rechercher une étiquette client…',
                'no_matches' => 'Aucune étiquette ne correspond.',
                'updated' => 'Étiquettes client mises à jour.',
            ],
            'activity' => [
                'created' => 'Client ajouté',
                'created_meta' => 'Référence :ref',
                'consent' => 'Consentement marketing enregistré',
                'note' => 'Note ajoutée',
                'important_note' => 'Note importante ajoutée',
                'archived' => 'Client archivé',
                'title' => 'Activité',
                'search' => 'Rechercher dans l’activité du client…',
                'filters' => [
                    'all' => 'Toute l’activité',
                    'bookings' => 'Réservations',
                    'notes' => 'Notes',
                    'client' => 'Mises à jour du client',
                    'tags' => 'Étiquettes',
                    'payments' => 'Paiements',
                    'files' => 'Fichiers',
                ],
                'none' => 'Rien à afficher pour l’instant.',
                'no_matches' => 'Aucune activité ne correspond.',

                /*
                | Une ligne par chose qui peut arriver.
                |
                | Écrit comme ce qui s'est passé plutôt que comme ce que
                | quelqu'un a fait : « Réservation reportée » est le fait, et
                | qui l'a fait est la ligne en dessous — c'est aussi là que
                | StyleDesk lui-même apparaît quand personne ne l'a fait.
                */
                'events' => [
                    'booking_created' => 'Réservation créée',
                    'booking_rescheduled' => 'Réservation reportée',
                    'booking_cancelled' => 'Réservation annulée',
                    'booking_checked_in' => 'Client arrivé',
                    'booking_completed' => 'Rendez-vous terminé',
                    'booking_no_show' => 'Réservation marquée comme absence',
                    'booking_declined' => 'Réservation refusée',
                    'note_added' => 'Note ajoutée',
                    'note_updated' => 'Note modifiée',
                    'note_deleted' => 'Note supprimée',
                    'note_marked_important' => 'Note marquée importante',
                    'note_unmarked_important' => 'Note n’est plus importante',
                    'note_made_private' => 'Note passée en privée',
                    'note_made_shared' => 'Note rendue visible à l’équipe',
                    'client_updated' => 'Informations client mises à jour',
                    'tag_added' => 'Étiquette ajoutée',
                    'tag_removed' => 'Étiquette retirée',
                    'payment_due' => 'Paiement dû',
                    'payment_received' => 'Paiement reçu',
                    'payment_partial' => 'Paiement partiel reçu',
                    'file_uploaded' => 'Fichier ajouté',
                    'file_updated' => 'Détails du fichier modifiés',
                    'file_replaced' => 'Fichier remplacé',
                    'file_deleted' => 'Fichier supprimé',
                    'file_viewed' => 'Fichier ouvert',
                    'file_downloaded' => 'Fichier téléchargé',
                    'file_record_created' => 'Avant / après enregistré',
                    'file_record_updated' => 'Avant / après mis à jour',
                    'file_record_deleted' => 'Avant / après supprimé',
                ],

                /* Les noms de champs qu'une personne reconnaît sur le formulaire. */
                'fields' => [
                    'when' => 'Rendez-vous',
                    'first_name' => 'Prénom',
                    'last_name' => 'Nom',
                    'preferred_name' => 'Nom d’usage',
                    'email' => 'Adresse e-mail',
                    'mobile' => 'Numéro de mobile',
                    'phone' => 'Numéro de téléphone',
                    'date_of_birth' => 'Date de naissance',
                    'address' => 'Adresse',
                    'city' => 'Ville',
                    'state' => 'Région',
                    'postal_code' => 'Code postal',
                    'country' => 'Pays',
                    'status' => 'Statut du client',
                    'preferred_location_id' => 'Établissement préféré',
                    'preferred_staff_id' => 'Praticien préféré',
                    'gender' => 'Genre',
                    'pronouns' => 'Pronoms',
                    'comm_email' => 'Contact par e-mail',
                    'comm_sms' => 'Contact par SMS',
                    'comm_phone' => 'Contact par téléphone',
                    'marketing_email' => 'Marketing · e-mail',
                    'marketing_sms' => 'Marketing · SMS',
                ],

                'system' => 'Système StyleDesk',
                'by' => 'par :name',
                'private_note' => 'Note privée',
                'private_hidden' => 'Masquée — vous n’avez pas accès à cette note.',
                'fields_changed' => ':count champs modifiés',
                'one_field_changed' => '1 champ modifié',
                'view_changes' => 'Voir les modifications',
                'hide_changes' => 'Masquer les modifications',
                'previous' => 'Avant',
                'new' => 'Après',
                'empty_value' => 'Non renseigné',
                'view_booking' => 'Voir la réservation',
                'refund_soon' => 'Remboursement — bientôt',
                'refund_hint' => 'Les remboursements rejoindront cette chronologie à l’arrivée de la fonctionnalité.',
            ],
            'bookings' => [
                'all_dates' => 'Toutes les dates',
                'when' => 'Quelles réservations',
                'all_bookings' => 'Toutes',
                'completed' => 'Terminées',
                'with' => 'avec :name',
                'view_upcoming' => 'Voir les rendez-vous à venir',
                'all_years' => 'Toutes les années',
                'all_months' => 'Tous les mois',
                'all_services' => 'Toutes les prestations',
                'none_match' => 'Aucune réservation ne correspond à ces filtres.',
                'view_full' => 'Voir la réservation complète',
                'upcoming' => 'À venir',
                'previous' => 'Visites précédentes',
                'none_upcoming' => 'Aucun rendez-vous à venir.',
                'none_previous' => 'Aucune visite pour l’instant.',
                'coming' => 'L’historique des rendez-vous apparaîtra ici à l’arrivée des réservations.',
                'hidden' => 'Vous n’avez pas accès à l’historique des rendez-vous de ce client.',
                'next_appointment' => 'Prochain rendez-vous',
                'last_booking' => 'Dernière réservation',
                'view_booking' => 'Voir la réservation',
                'reschedule' => 'Reporter',
                'book_same_again' => 'Reprendre le même rendez-vous',
            ],
            'notes' => [
                'title' => 'Notes',
                'add' => 'Ajouter une note',
                'placeholder' => 'Que doit savoir la prochaine personne ?',
                'important' => 'Marquer comme importante',
                'important_badge' => 'Importante',
                'save' => 'Enregistrer la note',
                'cancel' => 'Annuler',
                'edit' => 'Modifier',
                'delete' => 'Supprimer',
                'delete_confirm' => 'Supprimer cette note ? Elle est retirée de la fiche du client pour tout le monde.',
                'delete_tip' => 'Supprimer la note',
                'delete_title' => 'Supprimer la note ?',
                'none' => 'Aucune note sur ce client pour l’instant.',
                'hidden' => 'Vous n’avez pas accès aux notes de ce client.',
                'added' => 'Note ajoutée',
                'updated' => 'Note modifiée',
                'deleted' => 'Note supprimée',
                'body_required' => 'Écrivez la note avant de l’enregistrer.',
                'unknown_author' => 'Un ancien membre de l’équipe',
                'yours' => 'Vous',
                'important_hint' => 'Affichée sur la fiche elle-même, pour être lue avant le rendez-vous plutôt que pendant.',
                'private' => 'Marquer comme privée',
                'private_hint' => 'Seuls vous, le propriétaire, les administrateurs et les personnes que vous nommez ci-dessous peuvent la lire.',
                'private_badge' => 'Note privée',
                'access' => 'Accès',
                'access_for' => 'Accès pour',
                'access_hint' => 'Les propriétaires et administrateurs peuvent toujours lire les notes privées. Toute autre personne doit être nommée ici.',
                'access_search' => 'Rechercher des membres de l’équipe…',
                'access_no_matches' => 'Personne ne correspond.',
                'access_placeholder' => 'Rechercher et sélectionner des membres…',
                'manage_access' => 'Gérer les accès',
                'column_user' => 'Membre de l’équipe',
                'column_role' => 'Rôle',
                'column_access' => 'Accès',
                'access_admins' => 'Les propriétaires et administrateurs ont toujours accès aux notes privées, il n’est donc pas nécessaire de les choisir ici.',
                'picker_title' => 'Sélectionner des personnes pour cette note privée',
                'assign' => 'Attribuer',
                'selected' => '{0} Personne de sélectionné|{1} 1 sélectionné|[2,*] :count sélectionnés',
                'access_summary' => '{1} 1 personne peut la lire|[2,*] :count personnes peuvent la lire',
                'remove_access_title' => 'Supprimer les réglages d’accès privé ?',
                'remove_access' => 'Désactiver la confidentialité efface les personnes que vous avez choisies.',
                'expand' => 'Déplier la note',
                'restore' => 'Restaurer la note',
                'access_clear' => 'Tout effacer',
                'access_none' => 'Personne de sélectionné pour l’instant. Les propriétaires et administrateurs peuvent tout de même la lire.',
                'toolbar' => 'Mise en forme',
                'bold' => 'Gras',
                'italic' => 'Italique',
                'underline' => 'Souligné',
                'heading' => 'Titre',
                'bullet_list' => 'Liste à puces',
                'ordered_list' => 'Liste numérotée',
                'link' => 'Lien',
                'link_url' => 'Adresse du lien',
                'image' => 'Insérer une image',
                'clear_formatting' => 'Effacer la mise en forme',
                'undo' => 'Annuler',
                'redo' => 'Rétablir',
                'editor_label' => 'Note',
                'image_uploading' => 'Envoi de l’image…',
                'image_failed' => 'Cette image n’a pas pu être envoyée.',
                'image_too_large' => 'Cette image dépasse 5 Mo.',
                'image_type' => 'Les images doivent être au format JPG, PNG ou WEBP.',
                'discard_title' => 'Abandonner la note non enregistrée ?',
                'discard_body' => 'Vos modifications seront perdues.',
                'keep_editing' => 'Continuer à modifier',
                'view' => 'Voir',
                'hide' => 'Masquer',
                'no_access' => 'Vous n’avez pas accès à cette note privée.',
                'count' => '{0} Aucune note|{1} 1 note|[2,*] :count notes',
                'discard_confirm' => 'Abandonner cette note ? Ce que vous avez écrit n’est pas enregistré.',
                'discard' => 'Abandonner',
                'added_success' => 'Note ajoutée.',
            ],
            'files' => [
                'title' => 'Fichiers',
                'intro' => 'Formulaires de consentement, documents de consultation, photos de soins et tout ce dont la fiche de ce client a besoin.',
                'private_note' => 'Les fichiers d’un client sont privés à votre équipe et ne servent jamais au marketing.',
                'no_access' => 'Vous n’avez pas la permission de voir les fichiers de ce client.',
                'add' => 'Ajouter un fichier',
                'draft' => 'Brouillon',
                'workflow' => [
                    'title' => 'Ajouter un fichier',
                    'choose' => 'Qu’ajoutez-vous ?',
                    'details' => 'Détails du fichier',
                    'treatment_details' => 'Détails du soin',
                    'back' => 'Retour',
                    'close' => 'Fermer',
                    'save_draft' => 'Enregistrer comme brouillon',
                ],
                'draft_saved' => 'Enregistré comme brouillon. Vous pourrez le terminer plus tard.',
                'untitled' => 'Soin sans titre',
                'discard_title' => 'Abandonner les modifications ?',
                'discard_body' => 'Ce que vous avez saisi ici n’est pas enregistré.',
                'discard_confirm' => 'Abandonner les modifications',
                'keep_editing' => 'Continuer à modifier',
                'views' => [
                    'all' => 'Tous les fichiers',
                    'records' => 'Avant / après',
                ],
                'empty' => 'Aucun fichier sur ce client pour l’instant.',
                'empty_records' => 'Aucun enregistrement avant / après pour l’instant.',
                'empty_hint' => 'Ajoutez un formulaire de consentement, un document de consultation ou une série de photos de soin.',
                'no_results' => 'Aucun fichier ne correspond à votre recherche.',

                'kinds' => [
                    'standard' => 'Envoi simple',
                    'standard_hint' => 'Un document, une image, ou plusieurs à la fois.',
                    'record' => 'Avant / après',
                    'record_hint' => 'Des photos de soin, gardées ensemble comme un seul enregistrement.',
                ],

                'types' => [
                    'image' => 'Image',
                    'document' => 'Document',
                    'before-after' => 'Avant / après',
                ],

                'sides' => [
                    'before' => 'Avant',
                    'after' => 'Après',
                ],

                'columns' => [
                    'preview' => 'Fichier',
                    'name' => 'Nom du fichier',
                    'type' => 'Type',
                    'category' => 'Catégorie',
                    'service' => 'Prestation liée',
                    'booking' => 'Réservation liée',
                    'uploaded_on' => 'Ajouté le',
                    'uploaded_by' => 'Ajouté par',
                    'actions' => 'Action',
                ],

                'fields' => [
                    'name' => 'Nom du fichier',
                    'name_placeholder' => 'Formulaire de consentement',
                    'file' => 'Fichier',
                    'files' => 'Fichiers',
                    'note' => 'Note',
                    'note_placeholder' => 'Ce que la prochaine personne devrait savoir sur ce fichier.',
                    'category' => 'Catégorie',
                    'service' => 'Prestation',
                    'booking' => 'Réservation',
                    'year' => 'Année',
                    'month' => 'Mois',
                    'all_years' => 'Toute année',
                    'all_months' => 'Tout mois',
                    'title' => 'Nom du soin',
                    'title_placeholder' => 'Soin hydratant du visage',
                    'treatment_date' => 'Date du soin',
                    'staff' => 'Praticien',
                    'before' => 'Images avant',
                    'after' => 'Images après',
                    'none' => 'Aucun',
                ],

                'drop' => 'Glissez des fichiers ici, ou',
                'drop_images' => 'Glissez des images ici, ou',
                'browse' => 'parcourez',
                'accepted' => 'JPG, PNG, WEBP, PDF, DOC, DOCX, XLS ou XLSX.',
                'accepted_images' => 'JPG, PNG ou WEBP.',
                'remove' => 'Retirer',
                'selected' => '{0} Aucun fichier choisi|{1} 1 fichier choisi|[2,*] :count fichiers choisis',
                'images_count' => '{0} Aucune image|{1} 1 image|[2,*] :count images',

                'actions' => [
                    'view' => 'Voir',
                    'download' => 'Télécharger',
                    'edit' => 'Modifier',
                    'replace' => 'Remplacer le fichier',
                    'delete' => 'Supprimer',
                    'details' => 'Voir les détails',
                    'continue' => 'Continuer à modifier',
                    'add_before' => 'Ajouter des images avant',
                    'add_after' => 'Ajouter des images après',
                    'edit_details' => 'Modifier les détails du soin',
                    'delete_record' => 'Supprimer l’enregistrement',
                    'menu' => 'Actions sur le fichier',
                ],

                'viewer' => [
                    'side_by_side' => 'Côte à côte',
                    'all' => 'Toutes les images',
                    'before_only' => 'Avant',
                    'after_only' => 'Après',
                    'previous' => 'Image précédente',
                    'next' => 'Image suivante',
                    'zoom_in' => 'Zoom avant',
                    'zoom_out' => 'Zoom arrière',
                    'reset' => 'Réinitialiser le zoom',
                    'close' => 'Fermer',
                    'position' => ':index / :total',
                    'no_preview' => 'Ce fichier ne peut pas être affiché ici.',
                    'open' => 'Ouvrir le fichier',
                    'information' => 'Informations sur le fichier',
                    'empty_side' => 'Rien de ce côté pour l’instant.',
                ],

                'categories' => [
                    'consent' => 'Formulaire de consentement',
                    'consultation' => 'Consultation',
                    'medical' => 'Médical / anamnèse',
                    'treatment' => 'Fiche de soin',
                    'receipt' => 'Reçu',
                    'identification' => 'Pièce d’identité',
                    'other' => 'Autre',
                ],

                'filters' => [
                    'all' => 'Tous les types',
                    'search' => 'Rechercher un fichier',
                ],

                'save' => 'Enregistrer',
                'cancel' => 'Annuler',
                'uploading' => 'Envoi en cours…',
                'processing' => 'Enregistrement des fichiers…',

                'confirm_delete' => 'Supprimer ce fichier ?',
                'confirm_delete_body' => 'Il est retiré de la fiche de ce client. La suppression reste dans son historique.',
                'confirm_delete_record' => 'Supprimer cet enregistrement avant / après ?',
                'confirm_delete_record_body' => 'Chaque image qu’il contient est retirée. La suppression reste dans l’historique de ce client.',
                'confirm' => 'Supprimer',
                'keep' => 'Conserver',

                'uploaded' => '{1} Fichier ajouté.|[2,*] :count fichiers ajoutés.',
                'updated' => 'Fichier mis à jour.',
                'replaced' => 'Fichier remplacé.',
                'deleted' => 'Fichier supprimé.',
                'record_saved' => 'Enregistrement avant / après sauvegardé.',
                'record_updated' => 'Enregistrement avant / après mis à jour.',
                'record_deleted' => 'Enregistrement avant / après supprimé.',

                'name_required' => 'Donnez un nom à ce fichier.',
                'file_required' => 'Choisissez au moins un fichier.',
                'title_required' => 'Donnez un nom à ce soin.',
                'record_needs_image' => 'Ajoutez au moins une image avant ou après.',
                'failed' => 'L’enregistrement a échoué. Réessayez.',
                'too_large' => ':files dépasse :size et n’a pas été ajouté.',

                'recent' => [
                    'title' => 'Fichiers récents',
                    'view_all' => 'Voir tous les fichiers',
                    'none' => 'Rien de classé sur ce client pour l’instant.',
                    'latest_treatment' => 'Dernier avant / après',
                ],
            ],
            'insights' => [
                'title' => 'Aperçus client',
                'coming' => 'Les habitudes de réservation apparaîtront ici dès que ce client aura des visites.',
            ],
        ],
        'validation' => [
            'phone_repeated' => 'Ce numéro figure déjà sur ce client.',
            'email_repeated' => 'Cette adresse figure déjà sur ce client.',
            'mobile_required' => 'Un numéro de téléphone est obligatoire.',
            'email_required' => 'Une adresse e-mail est obligatoire.',
            'first_name_required' => 'Le prénom est obligatoire.',
            'email_taken' => 'Un client avec cette adresse e-mail existe déjà.',
            'email_invalid' => 'Saisissez une adresse e-mail valide.',
            'dob_past' => 'Une date de naissance doit être dans le passé.',
        ],
    ],
];
