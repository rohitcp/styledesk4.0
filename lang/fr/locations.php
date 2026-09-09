<?php

declare(strict_types=1);

/*
| Le module Établissements : la grille de cartes, l'écran de consultation et le
| formulaire d'ajout/modification.
|
| Les libellés de champs sont volontairement partagés par les trois. « Code de
| l'établissement » désignant une chose sur une carte et une autre sur le
| formulaire, c'est la dérive qu'une clé unique existe pour empêcher.
*/

return [
    'title' => 'Établissements',
    'intro' => 'Ouvrez un établissement pour voir son responsable, ses horaires, ses prestations, son équipe et ses réglages de réservation.',
    'summary' => '{1} :count établissement actif|[2,*] :count établissements actifs',
    'summary_inactive' => ':count inactifs',

    'add' => 'Ajouter un établissement',
    'add_first' => 'Ajoutez votre premier établissement',
    'add_title' => 'Ajouter un établissement',
    'add_intro' => 'L’adresse, les coordonnées, le responsable et les horaires de la succursale. Les prestations, l’affectation de l’équipe et les règles de réservation se configurent une fois l’établissement créé.',
    'edit' => 'Modifier l’établissement',
    'edit_title' => 'Modifier l’établissement',
    'view' => 'Voir l’établissement',
    'deactivate' => 'Désactiver l’établissement',
    'activate' => 'Activer l’établissement',

    'created' => 'Établissement créé.',
    'saved' => 'Établissement enregistré.',
    'save_failed' => 'Impossible d’enregistrer vos modifications. Vérifiez les informations et réessayez.',
    'correct_fields' => 'Corrigez les champs signalés et réessayez.',
    'made_inactive' => ':name est désormais inactif. Il ne prend plus de réservations ; son historique est inchangé.',
    'made_active' => ':name est de nouveau actif.',

    'search_placeholder' => 'Rechercher par nom, code, ville ou adresse',
    'search_label' => 'Rechercher un établissement',
    'all_statuses' => 'Tous les statuts',
    'actions_for' => 'Actions pour :name',

    'empty_title' => 'Aucun établissement pour l’instant.',
    'empty_body' => 'Ajoutez la succursale depuis laquelle vous travaillez, pour que l’équipe, les prestations et les réservations aient un rattachement.',
    'no_matches' => 'Aucun établissement ne correspond à votre recherche.',
    'no_matches_hint' => 'Essayez un autre mot, ou effacez les filtres.',
    'clear_search' => 'Effacer la recherche',

    'inactive_notice' => 'Cet établissement est inactif. Il ne prend plus de réservations et n’apparaît pas en réservation en ligne. Ses rendez-vous passés, ses transactions et l’historique de son équipe sont inchangés.',

    'cards' => [
        'information' => 'Informations sur l’établissement',
        'address' => 'Adresse',
        'contact' => 'Coordonnées',
        'contact_hint' => 'Utilisées à la place des coordonnées principales de l’entreprise partout où cette succursale est nommée.',
        'manager' => 'Responsable de l’établissement',
        'manager_hint' => 'Qui est responsable de cette succursale. Nommer quelqu’un ici ne change pas ce qu’il peut faire dans StyleDesk — cela dépend de son rôle sous Rôles et permissions.',
        'manager_hint_short' => 'Qui est responsable de cette succursale. Cela ne change pas ce qu’il peut faire dans StyleDesk.',
        'hours' => 'Horaires d’ouverture',
        'hours_hint' => 'Les heures sont dans le fuseau propre à cet établissement, :timezone.',
        'elsewhere' => 'Configuré ailleurs',
        'elsewhere_hint' => 'Ceux-ci suivent les réglages de l’entreprise jusqu’à l’arrivée des réglages par établissement.',
    ],

    'fields' => [
        'name' => 'Nom de l’établissement',
        'code' => 'Code de l’établissement',
        'code_hint' => 'Un nom court qui distingue cette succursale sur les rapports et les reçus.',
        'type' => 'Type d’établissement',
        'primary' => 'Établissement principal',
        'primary_hint' => 'La succursale principale de l’entreprise. Le définir ici le retire de celle qui le porte actuellement.',
        'status' => 'Statut',
        'status_hint' => 'Un établissement inactif ne prend plus de réservations et n’apparaît pas en réservation en ligne. Son historique est conservé.',

        'address_line1' => 'Adresse ligne 1',
        'address_line2' => 'Adresse ligne 2',
        'suite' => 'Bâtiment / lot',
        'city' => 'Ville',
        'state' => 'Région / département',
        'postal_code' => 'Code postal',
        'country' => 'Pays',
        'timezone' => 'Fuseau horaire',
        'timezone_hint' => 'Les horaires et les réservations de cette succursale sont lus dans ce fuseau.',

        'manager' => 'Responsable de l’établissement',
        'assistants' => 'Adjoints',
        'assistants_hint' => 'Toute personne déjà choisie comme responsable ci-dessus n’est pas listée deux fois.',

        'phone' => 'Numéro de téléphone principal',
        'phone_short' => 'Téléphone principal',
        'phone_secondary' => 'Téléphone secondaire',
        'email' => 'E-mail de l’établissement',
        'booking_email' => 'E-mail de contact réservations',
        'support_email' => 'E-mail du service client',
        'website' => 'Site web',
        'extension' => 'Poste interne',
        'contact_person' => 'Personne à contacter',

        'contact' => 'Contact',
        'today' => 'Aujourd’hui',
    ],

    'placeholders' => [
        'name' => 'Salon du centre-ville',
        'code' => 'DT01',
        'type' => 'Non renseigné',
        'country' => 'Choisissez un pays',
        'timezone' => 'Choisissez un fuseau horaire',
        'manager' => 'Non assigné',
    ],

    'not_assigned' => 'Non assigné',
    'no_phone' => 'Aucun numéro de téléphone',
    'closed' => 'Fermé',
    'closed_today' => 'Fermé aujourd’hui',
    'no_active_staff' => 'Aucun membre actif pour l’instant.',
    'no_active_staff_link' => 'Ajoutez un membre',
    'no_active_staff_tail' => 'et vous pourrez nommer un responsable ici.',

    'elsewhere' => [
        'holidays' => 'Jours fériés et horaires exceptionnels',
        'holidays_value' => 'Suit les horaires d’ouverture',
        'staff' => 'Équipe affectée',
        'staff_value' => '{1} :count membre a cet établissement comme principal|[2,*] :count membres ont cet établissement comme principal',
        'services' => 'Prestations proposées',
        'services_value' => 'Toutes les prestations de l’entreprise',
        'resources' => 'Ressources et salles',
        'resources_value' => 'Pas encore configuré',
        'booking' => 'Réglages de réservation',
        'currency' => 'Devise et langue',
        'uses_business' => 'Utilise les réglages de l’entreprise',
        'link_hours' => 'Horaires d’ouverture →',
        'link_staff' => 'Personnel →',
        'link_services' => 'Prestations →',
        'link_business' => 'Entreprise →',
        'link_permissions' => 'Permissions →',
    ],

    'confirm' => [
        'deactivate' => 'Rendre :name inactif ? Il cesse de prendre des réservations et disparaît de la réservation en ligne. Ses rendez-vous passés, transactions et historique d’équipe sont conservés.',
        'activate' => 'Rendre :name de nouveau actif ? Il pourra prendre des réservations et apparaîtra en réservation en ligne.',
    ],

    'validation' => [
        'name_required' => 'Le nom de l’établissement est obligatoire.',
        'code_unique' => 'Un autre établissement utilise déjà ce code.',
        'status_required' => 'Indiquez si cet établissement est actif.',
        'address_required' => 'L’adresse ligne 1 est obligatoire.',
        'city_required' => 'La ville est obligatoire.',
        'state_required' => 'La région ou le département est obligatoire.',
        'postal_required' => 'Le code postal est obligatoire.',
        'country_required' => 'Choisissez un pays.',
        'country_in' => 'Choisissez un pays dans la liste.',
        'timezone_required' => 'Choisissez un fuseau horaire.',
        'timezone_in' => 'Choisissez un fuseau horaire dans la liste.',
        'phone_required' => 'Le numéro de téléphone principal est obligatoire.',
        'email_required' => 'L’e-mail de l’établissement est obligatoire.',
        'email_invalid' => 'Saisissez une adresse e-mail valide.',
        'url_invalid' => 'Saisissez une URL valide, avec https://',
        'closes_after_opens' => 'L’heure de fermeture doit être après l’heure d’ouverture.',
        'staff_invalid' => 'Choisissez l’un de vos propres membres.',
    ],

    /*
     * Les listes de types et de statuts, et les jours de la semaine.
     *
     * Clés identiques à la valeur enregistrée en base, si bien que la liste
     * déroulante et la règle de validation lisent la même liste tandis que
     * chaque langue décide du nom des options.
     */
    'types' => [
        'salon' => 'Salon',
        'spa' => 'Spa',
        'barbershop' => 'Barbier',
        'clinic' => 'Clinique',
        'studio' => 'Studio',
        'mobile' => 'À domicile',
        'other' => 'Autre',
    ],

    'statuses' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
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

    'hours_card' => 'Horaires de l’établissement',
    'hours_card_hint' => 'Quand cette succursale est ouverte, dans son propre fuseau. Ajoutez une seconde plage à un jour qui ferme en milieu de journée.',

    /*
     * Les contrôles propres au module d'horaires.
     *
     * Passés en props plutôt que lus dans le composant : le serveur connaît la
     * langue du lecteur, et une île Vue qui embarquerait sa propre copie de
     * chaque chaîne serait un second endroit à traduire.
     */
    'hours_editor' => [
        'copy_monday' => 'Copier lundi sur mar.–ven.',
        'open' => 'Ouvert',
        'closed' => 'Fermé',
        'closed_all_day' => 'Fermé toute la journée',
        'add_period' => 'Ajouter une plage',
        'to' => 'à',
        'remove_period' => 'Retirer cette plage du :day',
        'opening_time' => 'Heure d’ouverture du :day',
        'closing_time' => 'Heure de fermeture du :day',
        'copied' => 'Les horaires du lundi ont été appliqués du mardi au vendredi.',
    ],
];
