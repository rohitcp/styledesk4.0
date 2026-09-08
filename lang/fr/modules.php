<?php

declare(strict_types=1);

/*
| La page d'accueil des réglages : titres de groupes et cartes de modules.
|
| Chaque valeur anglaise reprise ici est la chaîne que config/app_settings.php
| portait déjà. C'est une couche de traduction, pas une réécriture — une valeur
| qui différerait changerait la copie du produit sous couvert d'ajouter une
| langue.
|
| La config garde ses propres littéraux comme dernier recours, si bien qu'un
| module ajouté sans traduction affiche son propre nom plutôt qu'une clé.
*/

return [
    'groups' => [
        'business_setup' => [
            'name' => 'Configuration de l’entreprise',
            'description' => 'Qui vous êtes, où vous travaillez et comment vous présentez l’entreprise.',
        ],
        'booking_operations' => [
            'name' => 'Réservation et exploitation',
            'description' => 'Les règles qui décident de ce qui peut être réservé, par qui et quand.',
        ],
        'clients_experience' => [
            'name' => 'Clients et expérience',
            'description' => 'Ce que vos clients voient, remplissent et achètent.',
        ],
        'communication' => [
            'name' => 'Communication',
            'description' => 'Ce que StyleDesk envoie, à qui, et comment cela se lit.',
        ],
        'finance' => [
            'name' => 'Finances',
            'description' => 'Encaisser, et tout ce qui s’ensuit.',
        ],
        'administration' => [
            'name' => 'Administration',
            'description' => 'Qui peut faire quoi, et ce qu’il advient de vos données.',
        ],
        'developer_integrations' => [
            'name' => 'Développement et intégrations',
            'description' => 'Connecter StyleDesk à tout le reste.',
        ],
    ],

    'modules' => [
        'business' => [
            'name' => 'Entreprise',
            'description' => 'Nom, type, coordonnées et configuration d’exploitation.',
        ],
        'locations' => [
            'name' => 'Établissements',
            'description' => 'Succursales, adresses, responsables, horaires d’ouverture et coordonnées.',
        ],
        'business-hours' => [
            'name' => 'Horaires d’ouverture',
            'description' => 'Heures d’ouverture et de fermeture, journées coupées, jours fériés et fermetures temporaires.',
        ],
        'branding' => [
            'name' => 'Identité visuelle',
            'description' => 'Logo, favicon et couleurs de marque dans l’application, les e-mails et les reçus.',
        ],
        'languages' => [
            'name' => 'Langues',
            'description' => 'Définissez la langue principale de l’application et choisissez les langues supplémentaires proposées à votre équipe.',
        ],
        'currency' => [
            'name' => 'Devise',
            'description' => 'Votre devise principale et les devises secondaires dans lesquelles vous fixez vos prix.',
        ],
        'booking-rules' => [
            'name' => 'Règles de réservation',
            'description' => 'Intervalles, délais de prévenance, fenêtres de réservation et règles de rendez-vous.',
        ],
        'services' => [
            'name' => 'Catégories de prestations',
            'description' => 'Les catégories qui organisent votre carte : lesquelles sont proposées et dans quel ordre elles apparaissent.',
        ],
        'resources' => [
            'name' => 'Catégories de ressources',
            'description' => 'Les catégories qui regroupent vos biens réservables — fauteuils, salles, équipements — y compris lesquelles sont proposées et dans quel ordre.',
        ],
        'staff' => [
            'name' => 'Membres de l’équipe',
            'description' => 'Membres, établissements, horaires de travail, accès aux prestations et statut d’emploi.',
        ],
        'calendar-scheduling' => [
            'name' => 'Agenda et planification',
            'description' => 'Comportement de l’agenda, valeurs de planification par défaut et affichage des rendez-vous.',
        ],
        'cancellation-no-show' => [
            'name' => 'Annulation et absence',
            'description' => 'Politiques et délais d’annulation, frais et traitement des absences.',
        ],
        'clients' => [
            'name' => 'Clients',
            'description' => 'Valeurs par défaut, préférences et configuration des fiches clients.',
        ],
        'client-booking' => [
            'name' => 'Réservation par le client',
            'description' => 'L’expérience de réservation côté client et ce que les clients peuvent faire eux-mêmes.',
        ],
        'online-booking' => [
            'name' => 'Réservation en ligne',
            'description' => 'Disponibilité publique, comportement de la page et règles de réservation en ligne.',
        ],
        'forms' => [
            'name' => 'Formulaires',
            'description' => 'Formulaires d’accueil, de consentement et de consultation, et quand les clients doivent les remplir.',
        ],
        'memberships' => [
            'name' => 'Abonnements',
            'description' => 'Niveaux d’abonnement, avantages récurrents et application des abonnements.',
        ],
        'packages' => [
            'name' => 'Forfaits',
            'description' => 'Prestations groupées, vente des forfaits et décompte des séances.',
        ],
        'gift-cards' => [
            'name' => 'Cartes cadeaux',
            'description' => 'Montants, expiration, règles d’utilisation et valeurs par défaut.',
        ],
        'loyalty-rewards' => [
            'name' => 'Fidélité et récompenses',
            'description' => 'Points, récompenses, règles de gain et façon dont les clients les utilisent.',
        ],
        'notifications' => [
            'name' => 'Notifications',
            'description' => 'Comportement des notifications par e-mail et dans l’application, et qui est notifié de quoi.',
        ],
        'email-settings' => [
            'name' => 'Réglages e-mail',
            'description' => 'Nom et adresse d’expéditeur, adresse de réponse et valeurs par défaut.',
        ],
        'email-templates' => [
            'name' => 'Modèles d’e-mail',
            'description' => 'Confirmations, rappels, annulations, reports, invitations et e-mails de bienvenue.',
        ],
        'sms-settings' => [
            'name' => 'Réglages SMS',
            'description' => 'Expéditeur SMS, textes par défaut et moments d’envoi des messages.',
        ],
        'payments' => [
            'name' => 'Paiements',
            'description' => 'Moyens de paiement acceptés, acomptes, comportement et valeurs par défaut.',
        ],
        'taxes' => [
            'name' => 'Taxes',
            'description' => 'Taux, ce à quoi ils s’appliquent et valeurs par défaut liées aux taxes.',
        ],
        'tips' => [
            'name' => 'Pourboires',
            'description' => 'Options de pourboire, pourcentages suggérés et répartition.',
        ],
        'receipts-invoices' => [
            'name' => 'Reçus et factures',
            'description' => 'Numérotation, mise en forme et contenu des reçus et des factures.',
        ],
        'inventory' => [
            'name' => 'Stock',
            'description' => 'Valeurs par défaut, comportement en cas de stock bas et réglages des produits à la vente.',
        ],
        'roles-permissions' => [
            'name' => 'Rôles et permissions',
            'description' => 'Contrôlez ce à quoi accèdent propriétaires, administrateurs, responsables, accueil, praticiens et rôles personnalisés.',
        ],
        'security' => [
            'name' => 'Sécurité',
            'description' => 'Comportement des sessions, politiques de connexion et réglages de sécurité de l’application.',
        ],
        'data-privacy' => [
            'name' => 'Données et confidentialité',
            'description' => 'Conservation des données, consentement des clients et configuration de la confidentialité.',
        ],
        'import-export' => [
            'name' => 'Import et export',
            'description' => 'Faire entrer des données, les faire sortir et lancer des migrations.',
        ],
        'system-preferences' => [
            'name' => 'Préférences système',
            'description' => 'Comportement général de StyleDesk et valeurs par défaut de l’application.',
        ],
        'integrations' => [
            'name' => 'Intégrations',
            'description' => 'Intégrations tierces et services connectés.',
        ],
        'api-webhooks' => [
            'name' => 'API et webhooks',
            'description' => 'Accès à l’API, clés, points de terminaison webhook et intégrations pour développeurs.',
        ],
    ],

    /*
     * Les pastilles de statut des modules.
     *
     * Clés identiques au statut dans config/app_settings.php, si bien qu'un
     * nouveau statut est une clé ici plutôt qu'une branche dans une vue.
     */
    'statuses' => [
        'active' => 'Actif',
        'setup-required' => 'Configuration requise',
        'coming-soon' => 'Bientôt',
        'view-only' => 'Lecture seule',
    ],

    /*
     * Les chiffres sous une carte.
     *
     * Mis au pluriel avec la syntaxe | de Laravel plutôt qu'avec
     * Str::plural(), qui ne connaît que l'anglais. « 1 langue|langues » est la
     * même phrase en français et ne se déduit pas de l'anglaise.
     */
    'counts' => [
        'active_staff' => '{1} membre actif|[2,*] membres actifs',
        'pending_invites' => '{1} invitation en attente|[2,*] invitations en attente',
        'roles' => '{1} rôle|[2,*] rôles',
        'active_locations' => '{1} établissement actif|[2,*] établissements actifs',
        'upcoming_closures' => '{1} fermeture à venir|[2,*] fermetures à venir',
        'enabled_currencies' => '{1} devise|[2,*] devises',
        'enabled_languages' => '{1} langue|[2,*] langues',
    ],
];
