<?php

declare(strict_types=1);

/*
| Les libellés du catalogue du module Clients.
|
| Les clés sont la valeur enregistrée en base : config/clients.php reste donc
| la liste unique que lisent la liste déroulante, la règle de validation et le
| module Clients, tandis que chaque langue décide du nom des options.
|
| Chaque valeur anglaise reprise ici est le libellé que config/clients.php
| portait déjà.
*/

return [
    'fields' => [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'mobile' => 'Numéro de mobile',
        'email' => 'Adresse e-mail',
        'date_of_birth' => 'Date de naissance',
        'gender' => 'Genre',
        'address' => 'Adresse',
        'city' => 'Ville',
        'state' => 'Région / département',
        'postal_code' => 'Code postal',
        'country' => 'Pays',
        'preferred_location' => 'Établissement préféré',
        'preferred_staff' => 'Praticien préféré',
        'avatar' => 'Photo de profil',
        'notes' => 'Notes',
    ],

    'name_formats' => [
        'first_last' => 'Prénom, puis nom',
        'last_first' => 'Nom, puis prénom',
        'first_initial' => 'Prénom et initiale du nom',
        'preferred_last' => 'Nom d’usage, puis nom',
    ],

    'phone_types' => [
        'mobile' => 'Mobile',
        'home' => 'Domicile',
        'work' => 'Professionnel',
        'other' => 'Autre',
    ],

    'email_types' => [
        'personal' => 'Personnelle',
        'work' => 'Professionnelle',
        'other' => 'Autre',
    ],

    'statuses' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'archived' => 'Archivé',
    ],

    'default_statuses' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'communication_methods' => [
        'email' => 'E-mail',
        'sms' => 'SMS',
        'phone' => 'Téléphone',
        'none' => 'Aucune préférence',
    ],

    'marketing_defaults' => [
        'ask' => 'Demander au client',
        'in' => 'A consenti',
        'out' => 'A refusé',
    ],

    'duplicate_rules' => [
        'email' => 'Même adresse e-mail',
        'mobile' => 'Même numéro de mobile',
        'name_mobile' => 'Mêmes prénom, nom et numéro de mobile',
    ],

    'search_fields' => [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'mobile' => 'Numéro de mobile',
        'email' => 'Adresse e-mail',
        'client_id' => 'Identifiant client',
    ],

    'creation_sources' => [
        'client_list' => 'Liste des clients',
        'booking' => 'Écran de réservation',
        'calendar' => 'Agenda',
        'walk_in' => 'Réservation sans rendez-vous',
        'pos' => 'Point de vente',
    ],

    'booking_panels' => [
        'preferred_staff' => 'Praticien préféré',
        'preferred_location' => 'Établissement préféré',
        'preferences' => 'Préférences du client',
        'notes' => 'Notes importantes',
        'last_booking' => 'Dernier rendez-vous',
        'recent_visits' => 'Visites récentes',
        'recent_staff' => 'Praticiens vus récemment',
        'rating' => 'Note moyenne du client',
        'book_same_again' => 'Reprendre le même rendez-vous',
    ],

    'history_panels' => [
        'upcoming' => 'Rendez-vous à venir',
        'previous' => 'Rendez-vous passés',
        'cancelled' => 'Rendez-vous annulés',
        'no_shows' => 'Absences',
        'services' => 'Prestations déjà réservées',
        'staff' => 'Praticiens déjà réservés',
        'locations' => 'Établissements visités',
        'notes' => 'Notes du client',
        'preferences' => 'Préférences',
        'activity' => 'Activité de réservation',
    ],

    'tag_colors' => [
        'slate' => 'Ardoise',
        'violet' => 'Violet',
        'blue' => 'Bleu',
        'teal' => 'Turquoise',
        'green' => 'Vert',
        'amber' => 'Ambre',
        'rose' => 'Rose',
        'plum' => 'Prune',
    ],
];
