<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tableau de bord
|--------------------------------------------------------------------------
|
| Le tableau de bord répond à une question différente selon qui se connecte,
| donc le vocabulaire est écrit pour la personne qui le lit plutôt que pour
| les données en dessous. Un accueil demande qui est au comptoir ; un
| propriétaire demande comment se passe le mois.
|
*/

return [

    'title' => 'Tableau de bord',
    'greeting' => [
        'morning' => 'Bonjour',
        'afternoon' => 'Bon après-midi',
        'evening' => 'Bonsoir',
    ],

    'location' => 'Établissement',
    'all_locations' => 'Tous les établissements',

    'performance' => [
        'title' => 'Performance de l’établissement',
        'today' => 'Encaissé aujourd’hui',
        'month' => 'Encaissé ce mois-ci',
        'change' => 'vs. mêmes jours le mois dernier',
        'outstanding' => 'Restant dû',
        'bookings' => 'Réservations ce mois-ci',
        'average' => 'Réservation moyenne',
        /* Dit plutôt qu'affiché comme un zéro : un premier mois n'a rien à
           quoi se comparer, et « 0 % » se lirait comme une activité à
           l'arrêt. */
        'no_comparison' => 'Aucun chiffre pour le mois dernier',
    ],

    'bookings_today' => [
        'title' => 'Réservations du jour',
        'total' => 'Total',
        'pending_checkin' => 'Enregistrement en attente',
        'checked_in' => 'Arrivés',
        'completed' => 'Terminées',
        'cancelled' => 'Annulées',
        'no_show' => 'Absence',
    ],

    'checkin' => [
        'title' => 'Enregistrement',
        'none' => 'Personne n’attend d’être enregistré.',
        'none_hint' => 'Les rendez-vous apparaissent ici au fil de la journée.',
        'view_all' => 'Ouvrir la file',
        'action' => 'Enregistrer l’arrivée',
    ],

    'arriving' => [
        'title' => 'Arrivent bientôt',
        'none' => 'Personne n’est attendu dans l’heure.',
    ],

    'waiting' => [
        'title' => 'En attente',
        'none' => 'Personne n’attend.',
        'for' => 'Attend depuis :count min',
        'since' => 'Arrivé à :time',
        'too_long' => 'Attend depuis un moment',
    ],

    'staff_today' => [
        'title' => 'Au travail aujourd’hui',
        'none' => 'Personne n’est au planning aujourd’hui.',
        'shift' => 'Service',
        'now' => 'Avec un client',
        'next' => 'Suivant',
        'remaining' => ':count restants',
        'free' => 'Libre',
    ],

    'schedule_issues' => [
        'title' => 'Problèmes de planning',
        'none' => 'Aucun problème n’appelle votre attention.',
        'working_without_a_shift' => 'Travaille sans service au planning',
        'bookings_without_staff' => 'Rendez-vous sans personne d’assignée',
    ],

    'clients' => [
        'title' => 'Clients',
        'new_today' => 'Nouveaux aujourd’hui',
        'booked_today' => 'Attendus aujourd’hui',
        'returning_today' => 'De retour',
        'active' => 'Clients actifs',
    ],

    'services' => [
        'title' => 'Les plus réservées ce mois-ci',
        'none' => 'Rien n’a encore été réservé ce mois-ci.',
        'bookings' => ':count réservées',
    ],

    'payments' => [
        'title' => 'Paiements',
        'collected' => 'Encaissé aujourd’hui',
        'outstanding' => 'Restant dû',
        'partial' => 'Partiellement payé',
        'due_today' => 'Vus aujourd’hui et impayés',
        'none' => 'Rien n’est dû sur la journée.',
    ],

    'alerts' => [
        'title' => 'À traiter',
        'none' => 'Aucun problème n’appelle votre attention.',
        'late' => ':count client attendu a dépassé son horaire|:count clients attendus ont dépassé leur horaire',
        'waiting_too_long' => ':count client attend depuis un moment|:count clients attendent depuis un moment',
        'unpaid' => ':count rendez-vous vu aujourd’hui n’a pas été payé|:count rendez-vous vus aujourd’hui n’ont pas été payés',
        'unstaffed' => ':count rendez-vous du jour n’a personne d’assignée|:count rendez-vous du jour n’ont personne d’assignée',
    ],

    'my_next_client' => [
        'title' => 'Client suivant',
        'none' => 'Vous n’avez pas d’autre rendez-vous aujourd’hui.',
        'none_hint' => 'Tout ce qui sera réservé plus tard aujourd’hui apparaîtra ici.',
        'here' => 'Ici maintenant',
        'view_client' => 'Voir le client',
        'view_booking' => 'Voir la réservation',
    ],

    'my_day' => [
        'title' => 'Ma journée',
        'none' => 'Rien n’est réservé pour vous aujourd’hui.',
    ],

    'my_schedule' => [
        'title' => 'Mon service',
        'none' => 'Vous n’êtes pas au planning aujourd’hui.',
        'from' => 'De',
        'to' => 'Jusqu’à',
        'break' => 'Pause',
        'remaining' => 'Encore à venir',
    ],

    'my_performance' => [
        'title' => 'Ma journée jusqu’ici',
        'clients' => 'Clients',
        'completed' => 'Terminés',
        'average' => 'Prestation moyenne',
        'tips' => 'Pourboires',
        'minutes' => ':count min',
    ],

    'quick_actions' => [
        'title' => 'Actions rapides',
        'booking' => 'Créer une réservation',
        'client' => 'Ajouter un client',
        'checkin' => 'Enregistrer une arrivée',
        'staff' => 'Ajouter un membre',
        'service' => 'Ajouter une prestation',
        'schedule' => 'Gérer le planning',
        'calendar' => 'Voir l’agenda',
    ],

    /* La liste de mise en route, affichée jusqu'à ce que le dernier point soit
       fait ou que la liste soit écartée. */
    'getting_started' => [
        'title' => 'Mise en route',
        'intro' => 'Il reste quelques réglages. Vous pouvez y revenir quand vous voulez.',
        'dismiss' => 'Masquer',

        /*
        | La liste elle-même.
        |
        | Ici et non dans DashboardController, où c'étaient neuf littéraux
        | anglais : une liste construite en PHP reste du texte à l'écran, et un
        | lecteur en chinois recevait toute la liste en anglais alors que la
        | carte autour d'elle était traduite.
        */
        'items' => [
            'service' => 'Ajoutez votre première prestation',
            'team' => 'Ajoutez les membres de l’équipe',
            'schedules' => 'Configurez les plannings',
            'client' => 'Ajoutez votre premier client',
            'online_booking' => 'Personnalisez la réservation en ligne',
            'payments' => 'Configurez les paiements',
            'reminders' => 'Configurez les rappels de rendez-vous',
            'branding' => 'Ajoutez votre logo et votre identité',
            'appointment' => 'Créez votre premier rendez-vous',
        ],
    ],
];
