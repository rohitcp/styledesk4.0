<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
|
| The diary drawn against the clock: who is working, who is booked, and where
| the gaps are.
|
*/

return [
    'title' => 'Calendrier',
    'subtitle' => 'Qui travaille, qui a rendez-vous et où sont les créneaux libres.',

    'nav' => [
        'previous' => 'Jour précédent',
        'next' => 'Jour suivant',
        'today' => 'Aujourd’hui',
        'pick' => 'Choisir une date',
    ],

    'views' => [
        'day' => 'Jour',
        'week' => 'Semaine',
        'month' => 'Mois',
    ],

    'filters' => [
        'location' => 'Établissement',
        'staff' => 'Équipe',
        'resource' => 'Ressource',
        'all_staff' => 'Toute l’équipe',
        'all_resources' => 'Toutes les ressources',
        'all_locations' => 'Tous les établissements',
        'interval' => 'Intervalle',
        'minutes' => ':count min',
        'service' => 'Prestation',
        'all_services' => 'Toutes les prestations',
        'search' => 'Rechercher…',
    ],

    'summary' => [
        'total' => 'Réservations',
        'arrived' => 'Arrivés',
        'completed' => 'Terminées',
        'cancelled' => 'Annulées',
        'no_show' => 'Absent',
        'owing' => 'Solde dû',
        'revenue' => 'Chiffre d’affaires prévu',
    ],

    'card' => [
        'balance' => 'Solde dû · :amount',
        'membership' => 'Abonnement',
        /* Reserved until the client walks in — the rule the redemption engine
           keeps. A calendar that called a future appointment "used" would be
           telling the client they had already had it. */
        'credits_reserved' => 'Abonnement · :count crédit réservé|Abonnement · :count crédits réservés',
        'credits_used' => 'Abonnement · :count crédit utilisé|Abonnement · :count crédits utilisés',
        'note' => 'Comporte une note',
    ],

    'more' => '+:count de plus',
    'preview' => [
        'status' => 'Statut',
        'payment' => 'Paiement',
    ],

    'now' => 'Maintenant · :time',
    'break' => 'Pause de :minutes min',
    'off' => 'Ne travaille pas',
    'closed' => 'Fermé ce jour-là.',
    'no_staff' => 'Personne n’est encore configuré pour réaliser des prestations dans cet établissement.',
    'empty' => 'Aucune réservation ce jour-là.',
    'loading' => 'Chargement de la journée…',
    'new_booking' => '+ Nouvelle réservation',
    'free_slot' => 'Réserver avec :staff à :time',
];
