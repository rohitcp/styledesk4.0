<?php

declare(strict_types=1);

/* Mon compte — les réglages propres à la personne connectée. Volontairement
   séparé du fichier settings, qui appartient aux Réglages de l'application et
   configure l'entreprise plutôt que la personne. */

return [
    'title' => 'Mon compte',
    'intro' => 'Votre profil, vos préférences et votre sécurité. Rien ici ne change quoi que ce soit pour le reste de l’entreprise.',

    'sections' => [
        'profile' => 'Mon profil',
        'preferences' => 'Mes préférences',
        'password' => 'Changer de mot de passe',
        'notifications' => 'Notifications',
    ],

    'save' => 'Enregistrer les modifications',
    'cancel' => 'Annuler',
    'reset' => 'Rétablir les valeurs par défaut',
    'reset_confirm' => 'Rétablir les valeurs par défaut de l’entreprise ?',

    'profile' => [
        'title' => 'Mon profil',
        'intro' => 'Comment vous apparaissez dans StyleDesk, et comment nous vous joignons.',

        'photo_card' => 'Photo de profil',
        'photo_hint' => 'JPG, PNG ou WebP, jusqu’à 5 Mo. Vos initiales sont utilisées tant que vous n’en ajoutez pas.',
        'photo_upload' => 'Envoyer une photo',
        'photo_replace' => 'Remplacer la photo',
        'photo_remove' => 'Retirer',
        'photo_saved' => 'Photo de profil mise à jour.',
        'photo_removed' => 'Photo de profil retirée.',
        'photo_too_large' => 'Cette image dépasse 5 Mo.',
        'photo_wrong_type' => 'Choisissez une image JPG, PNG ou WebP.',
        'photo_pending' => 'Aperçu — enregistrez pour la conserver.',

        'personal_card' => 'Informations personnelles',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'display_name' => 'Nom affiché',
        'display_name_hint' => 'Laissez vide pour utiliser vos prénom et nom.',
        'display_name_placeholder' => 'Le nom que voient les clients',
        'job_title' => 'Intitulé de poste',
        'job_title_placeholder' => 'Coiffeur senior',
        'phone' => 'Numéro de mobile',
        'phone_hint' => 'Utilisé pour les alertes de rendez-vous une fois les SMS activés.',
        'saved' => 'Profil mis à jour.',

        'email_card' => 'Adresse e-mail',
        'email' => 'Adresse e-mail',
        'email_locked_hint' => 'L’adresse avec laquelle vous vous connectez. Demandez à un administrateur si elle doit changer.',
        'email_hint' => 'Vous vous connectez avec cette adresse. La changer demande votre mot de passe et une confirmation depuis la nouvelle adresse.',
        'email_current_password' => 'Mot de passe actuel',
        'email_change_cta' => 'Changer',
        'email_new' => 'Nouvelle adresse e-mail',
        'email_change' => 'Changer d’adresse e-mail',
        'email_subject' => 'Confirmez votre nouvelle adresse e-mail StyleDesk',
        'email_pending_title' => 'Confirmez votre nouvelle adresse e-mail',
        'email_pending_body' => 'Nous avons envoyé un lien à :email. Votre adresse actuelle continue de fonctionner jusqu’à ce que vous confirmiez la nouvelle.',
        'email_pending' => 'Consultez :email pour trouver le lien de confirmation.',
        'email_resend' => 'Renvoyer le lien',
        'email_resent' => 'Nous avons renvoyé le lien de confirmation.',
        'email_cancel' => 'Annuler le changement',
        'email_cancelled' => 'Changement d’adresse annulé.',
        'email_changed' => 'Adresse e-mail mise à jour.',
        'email_link_dead' => 'Ce lien de confirmation a expiré ou a déjà été utilisé. Demandez-en un nouveau depuis Mon profil.',
        'email_taken' => 'Cette adresse e-mail est déjà utilisée.',
        'email_unchanged' => 'C’est déjà votre adresse e-mail.',

        'staff_card' => 'Informations professionnelles',
        'staff_intro' => 'Définies par un administrateur dans la gestion de l’équipe et affichées ici pour référence.',
        'role' => 'Rôle',
        'no_role' => 'Aucun rôle attribué',
        'locations' => 'Établissement assigné',
        'all_locations' => 'Tous les établissements',
        'no_location' => 'Aucun établissement assigné',
        'status' => 'Statut du compte',
        'status_active' => 'Actif',
        'status_inactive' => 'Inactif',
        'status_archived' => 'Archivé',
        'member_since' => 'Membre depuis',
    ],

    'preferences' => [
        'title' => 'Mes préférences',
        'intro' => 'Comment l’application se présente pour vous. Chacune suit la valeur de l’entreprise tant que vous ne la changez pas ici.',

        'language_card' => 'Langue',
        'language' => 'Langue principale',
        'language_hint' => 'Ne change l’interface que pour vous. Ce que votre entreprise a saisi — noms de prestations, notes clients — n’est jamais traduit.',
        'language_default' => 'Utiliser la langue de l’entreprise',

        'format_card' => 'Dates et heures',
        'date_format' => 'Format de date',
        'time_format' => 'Format d’heure',
        'timezone' => 'Fuseau horaire',
        'timezone_hint' => 'Laissez vide pour suivre le fuseau de l’entreprise.',
        'first_day_of_week' => 'Début de semaine',
        'use_business' => 'Utiliser le réglage de l’entreprise',

        'calendar_card' => 'Agenda',
        'calendar_intro' => 'Comment les écrans d’agenda s’ouvrent pour vous.',
        'calendar_view' => 'Vue par défaut',
        'calendar_views' => [
            'day' => 'Jour',
            'week' => 'Semaine',
            'month' => 'Mois',
        ],
        'show_weekends' => 'Afficher les week-ends',
        'show_cancelled' => 'Afficher les rendez-vous annulés',
        'show_resource_color' => 'Afficher la couleur des ressources',
        'show_staff_color' => 'Afficher la couleur des praticiens',

        'save' => 'Enregistrer les préférences',
        'saved' => 'Préférences mises à jour.',
        'reset_action' => 'Rétablir les valeurs par défaut',
        'reset_hint' => 'Efface vos choix personnels pour que chaque réglage suive de nouveau l’entreprise.',
        'reset' => 'Préférences rétablies aux valeurs de l’entreprise.',
    ],

    'password' => [
        'title' => 'Changer de mot de passe',
        'intro' => 'Choisissez-en un que vous n’utilisez nulle part ailleurs.',
        'card' => 'Votre mot de passe',
        'hidden' => 'Votre mot de passe est masqué',
        'current' => 'Mot de passe actuel',
        'new' => 'Nouveau mot de passe',
        'confirm' => 'Confirmer le nouveau mot de passe',
        'requirements' => 'Votre mot de passe doit contenir',
        'save' => 'Changer le mot de passe',
        'saved' => 'Mot de passe changé.',
        'current_wrong' => 'Ce n’est pas votre mot de passe actuel.',
        'same_as_current' => 'Choisissez un mot de passe différent de l’actuel.',
        'mismatch' => 'Les mots de passe ne correspondent pas.',
        'logout_others' => 'Se déconnecter de tous les autres appareils',
        'logout_others_hint' => 'Ce navigateur reste connecté. Partout ailleurs, le nouveau mot de passe sera demandé.',
    ],

    'notifications' => [
        'title' => 'Notifications',
        'intro' => 'Quels messages vous parviennent, et comment. Les alertes de sécurité sont toujours envoyées.',
        'saved' => 'Préférences de notification mises à jour.',
        'reset' => 'Préférences de notification rétablies par défaut.',
        'save' => 'Enregistrer les préférences de notification',
        'enable_all' => 'Tout activer',
        'disable_all' => 'Désactiver les notifications optionnelles',
        'bulk_hint' => 'Les alertes de sécurité restent actives dans tous les cas.',
        'always_on' => 'Toujours actif',
        'coming_soon' => 'Bientôt',
        'not_supported' => 'Indisponible pour cette notification',

        'channels' => [
            'in_app' => 'Dans l’app',
            'email' => 'E-mail',
            'sms' => 'SMS',
            'push' => 'Push',
        ],

        'groups' => [
            'appointments' => [
                'label' => 'Rendez-vous',
                'description' => 'Ce qui arrive aux réservations de votre agenda.',
            ],
            'clients' => [
                'label' => 'Clients',
                'description' => 'Les changements chez les personnes dont vous vous occupez.',
            ],
            'team' => [
                'label' => 'Équipe',
                'description' => 'Votre planning, votre rôle et ce que vos collègues vous envoient.',
            ],
            'resources' => [
                'label' => 'Ressources',
                'description' => 'Les salles, fauteuils et équipements dont dépend votre travail.',
            ],
            'system' => [
                'label' => 'Sécurité et compte',
                'description' => 'Comment vous apprenez qu’il s’est passé quelque chose sur votre compte. Toujours envoyées.',
            ],
        ],

        'types' => [
            'booking.created' => 'Nouveau rendez-vous créé',
            'booking.assigned' => 'Rendez-vous qui m’est attribué',
            'booking.updated' => 'Rendez-vous modifié',
            'booking.rescheduled' => 'Rendez-vous reporté',
            'booking.cancelled' => 'Rendez-vous annulé',
            'booking.completed' => 'Rendez-vous terminé',
            'booking.no_show' => 'Rendez-vous marqué comme absence',
            'booking.reminder' => 'Rappel de rendez-vous',

            'client.assigned' => 'Nouveau client qui m’est attribué',
            'client.updated' => 'Fiche client mise à jour',
            'client.note_added' => 'Note client ajoutée',
            'client.file_uploaded' => 'Fichier client ajouté',
            'client.mentioned' => 'Une note client me mentionne',

            'team.invitation' => 'Invitation d’un membre',
            'team.location_assigned' => 'Membre affecté à un établissement',
            'team.hours_changed' => 'Horaires de travail modifiés',
            'team.schedule_changed' => 'Planning modifié',
            'team.mentioned' => 'Quelqu’un me mentionne',
            'team.role_changed' => 'Rôle ou permissions modifiés',

            'resource.assigned' => 'Ressource qui m’est attribuée',
            'resource.changed' => 'Ressource modifiée',
            'resource.unavailable' => 'Une ressource devient indisponible',
            'resource.conflict' => 'Conflit de réservation de ressource',

            'security.alert' => 'Alertes de sécurité',
            'security.password_changed' => 'Mot de passe changé',
            'security.email_changed' => 'Adresse e-mail changée',
            'security.new_login' => 'Nouvelle connexion détectée',
            'security.account_alert' => 'Alertes sur le compte',
        ],
        /*
        | Une ligne chacune, disant QUAND le message arrive plutôt que de
        | répéter le titre. Qui décide s'il veut être interrompu a besoin du
        | déclencheur, pas d'un synonyme du titre.
        */
        'types_hint' => [
            'booking.created' => 'Quelqu’un prend rendez-vous — en ligne, au comptoir ou par téléphone.',
            'booking.assigned' => 'Un rendez-vous est mis à votre nom, ou vous est transféré par un collègue.',
            'booking.updated' => 'Les prestations, le prix ou les notes d’un de vos rendez-vous changent.',
            'booking.rescheduled' => 'Un de vos rendez-vous passe à un autre jour ou une autre heure.',
            'booking.cancelled' => 'Un client ou un collègue annule un de vos rendez-vous.',
            'booking.completed' => 'Un de vos rendez-vous est encaissé et marqué terminé.',
            'booking.no_show' => 'Un client est enregistré comme n’étant pas venu.',
            'booking.reminder' => 'Peu avant qu’un de vos rendez-vous ne commence.',

            'client.assigned' => 'Un client vous est confié.',
            'client.updated' => 'Quelqu’un modifie la fiche d’un client qui vous est assigné.',
            'client.note_added' => 'Une note est ajoutée à l’un de vos clients.',
            'client.file_uploaded' => 'Une photo ou un document est ajouté à l’un de vos clients.',
            'client.mentioned' => 'Un collègue écrit votre nom dans une note sur un client.',

            'team.invitation' => 'Quelqu’un est invité à rejoindre l’entreprise, ou accepte une invitation.',
            'team.location_assigned' => 'Vous changez d’établissement, ou un collègue en change.',
            'team.hours_changed' => 'Vos horaires de travail habituels sont modifiés.',
            'team.schedule_changed' => 'Un planning couvrant vos services est publié ou modifié.',
            'team.mentioned' => 'Un collègue écrit votre nom quelque part dans StyleDesk.',
            'team.role_changed' => 'Votre rôle change, ou ce que vous avez le droit de faire change.',

            'resource.assigned' => 'Une salle, un fauteuil ou un équipement est mis à votre nom.',
            'resource.changed' => 'Une ressource que vous utilisez est renommée, déplacée ou voit ses horaires modifiés.',
            'resource.unavailable' => 'Une ressource où vous êtes réservé est fermée pour maintenance ou réparation.',
            'resource.conflict' => 'Deux rendez-vous finissent par avoir besoin de la même ressource en même temps.',

            'security.alert' => 'Il se passe sur votre compte quelque chose que vous devriez savoir.',
            'security.password_changed' => 'Votre mot de passe est changé, par vous ou par quelqu’un d’autre.',
            'security.email_changed' => 'L’adresse e-mail avec laquelle vous vous connectez est changée.',
            'security.new_login' => 'Votre compte est utilisé depuis un appareil ou un lieu inconnus.',
            'security.account_alert' => 'Votre compte est verrouillé, suspendu ou restreint.',
        ],
    ],
];
