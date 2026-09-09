<?php

declare(strict_types=1);

return [

    'title' => 'Ventes',
    'intro' => 'Consultez les recettes, les paiements, les soldes dus, les remboursements, les pourboires et l’activité des transactions.',

    'period' => 'Période',
    'periods' => [
        'today' => 'Aujourd’hui',
        'tomorrow' => 'Demain',
        'yesterday' => 'Hier',
        'last_3' => '3 derniers jours',
        'last_7' => '7 derniers jours',
        'week' => 'Cette semaine',
        'month' => 'Ce mois-ci',
        'custom' => 'Période personnalisée',
    ],
    'from' => 'Du',
    'to' => 'Au',
    'apply' => 'Appliquer',

    'widgets' => [
        'total_sales' => 'Ventes totales',
        'collected' => 'Paiements encaissés',
        'outstanding' => 'Solde dû',
        'refunds' => 'Remboursements',
        'tips' => 'Pourboires encaissés',
        'transactions' => 'Transactions',
    ],

    'notes' => [
        'total_sales' => 'Par date de rendez-vous',
        'collected' => 'Par date de paiement',
        'outstanding' => 'Encore dû',
        'refunds' => 'Par date de paiement',
        'tips' => 'Par date de paiement',
        'transactions' => 'Paiements encaissés',
    ],

    'vs_previous' => 'vs période précédente',
    'show_these' => 'Voir ceux-ci →',

    /*
    | Les deux moitiés sont comptées différemment et la page le dit. Un acompte
    | pris en août pour un rendez-vous de septembre est une vente de septembre
    | et un paiement d'août ; qui l'ignore trouvera que les totaux ne se
    | recoupent pas et conclura que les chiffres sont faux.
    */
    'counting_note' => 'Les ventes et les soldes dus sont comptés par date de rendez-vous. Les paiements, remboursements et pourboires le sont au moment où l’argent a bougé.',

    'transactions' => 'Transactions',
    'search_placeholder' => 'Transaction, réservation, client, e-mail ou téléphone',
    'actions_for' => 'Actions pour :name',
    'showing' => 'Affichage',
    'results' => [
        'zero' => 'Aucune transaction',
        'one' => '1 transaction',
        'many' => ':count transactions',
        'clear' => 'Effacer les filtres',
    ],
    'empty' => 'Aucun mouvement d’argent sur cette période.',
    'walk_in' => 'Sans rendez-vous',

    'columns' => [
        'reference' => 'Transaction',
        'at' => 'Date et heure',
        'booking' => 'Réservation',
        'client' => 'Client',
        'services' => 'Prestation',
        'staff' => 'Praticien',
        'location' => 'Établissement',
        'total' => 'Total de la réservation',
        /* Ce paiement-ci, par opposition à ce que la réservation a encaissé en
           tout — deux questions différentes, et le tableau répond aux deux. */
        'amount' => 'Payé',
        'balance' => 'Solde',
        'method' => 'Moyen',
        'status' => 'Statut',
    ],

    'drawer' => [
        'at_a_glance' => 'En un coup d’œil',
        'nothing_owed' => 'Rien de dû',
        'next_appointment' => 'Prochain rendez-vous',
        'total_visits' => 'Total des visites',
        'lifetime_spend' => 'Dépenses cumulées',
        'last_visit' => 'Dernière visite',
        'tags' => 'Étiquettes',
        'account' => 'Compte',
        'bookings' => 'Réservations',
        'spend' => 'Total payé',
        'owed' => 'Encore dû',
        'transaction' => 'Transaction',
        'booking' => 'Réservation',
        'amount' => 'Ce paiement',
        'tip' => 'Pourboire',
        'paid_total' => 'Payé sur cette réservation',
        'recorded_by' => 'Encaissé par',
        'online' => 'En ligne',
        'processor_reference' => 'Référence du prestataire',
        'view_client' => 'Voir tous les détails',
        'view_receipt' => 'Voir le reçu complet',
        'download' => 'Télécharger le PDF',
    ],

    'actions' => [
        'view_booking' => 'Voir la réservation',
        'open_booking' => 'Ouvrir la page de réservation',
        'view_client' => 'Voir le client',
        'view_receipt' => 'Voir le reçu',
    ],
];
