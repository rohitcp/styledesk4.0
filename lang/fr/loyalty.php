<?php

declare(strict_types=1);

/*
| Fidélité et récompenses.
|
| Deux publics, séparés par leurs clés de premier niveau : `settings` est lu par
| la personne qui décide du fonctionnement du programme, `client` par celle qui
| est au comptoir avec quelqu'un devant elle. C'est le second qui doit être
| court — l'accueil le lit pendant qu'un client attend.
*/

return [

    'title' => 'Fidélité et récompenses',

    /* Une ligne par chose qui peut arriver à un solde. Écrit comme ce qui s'est
       passé plutôt que comme une catégorie, parce que cela se lit dans une
       liste, à côté d'une date et d'un nombre. */
    'activities' => [
        'earned' => 'Points gagnés',
        'redeemed' => 'Récompense utilisée',
        'expired' => 'Points expirés',
        'refund_adjustment' => 'Ajustement pour remboursement',
        'cancellation_adjustment' => 'Ajustement pour annulation',
        'manual_add' => 'Ajout manuel',
        'manual_deduct' => 'Retrait manuel',
    ],

    /* Pourquoi quelqu'un a modifié un solde à la main. */
    'reasons' => [
        'customer_service' => 'Geste commercial',
        'promotion' => 'Promotion',
        'correction' => 'Correction',
        'duplicate' => 'Points en double',
        'refund' => 'Ajustement pour remboursement',
        'other' => 'Autre',
    ],

    'purchases' => [
        'services' => 'Prestations',
        'products' => 'Produits',
        'memberships' => 'Abonnements',
        'packages' => 'Forfaits',
        'gift_cards' => 'Cartes cadeaux',
        'tips' => 'Pourboires',
        'taxes' => 'Taxes',
    ],

    'expiry' => [
        'never' => 'Jamais',
        '6m' => 'Au bout de 6 mois',
        '12m' => 'Au bout de 12 mois',
        '24m' => 'Au bout de 24 mois',
    ],

    'notifications' => [
        'earned_email' => 'E-mail de points gagnés',
        'earned_sms' => 'SMS de points gagnés',
        'reward_email' => 'E-mail de récompense disponible',
        'reward_sms' => 'SMS de récompense disponible',
    ],

    /* ------------------------------------------------------- les réglages -- */

    'settings' => [
        'title' => 'Fidélité et récompenses',
        'intro' => 'Ce qu’une visite rapporte, ce qu’un point vaut en retour, et qui peut en dépenser un.',

        'enable' => 'Activer la fidélité et les récompenses',
        'enable_hint' => 'Les clients gagnent des points sur les rendez-vous terminés et payés, et peuvent les dépenser lors de visites suivantes.',
        'disabled_note' => 'Les récompenses sont désactivées. Rien de nouveau n’est gagné et rien ne peut être utilisé — chaque solde et chaque ligne d’historique est conservé tel quel.',

        'program' => 'Programme',
        'program_hint' => 'Le nom que vos clients voient sur leur reçu et dans leurs e-mails.',
        'program_name' => 'Nom du programme',
        'program_name_hint' => 'Par exemple : Glow Rewards, Points Beauté, Récompenses Bien-être.',
        'description' => 'Description',
        'description_hint' => 'Une ligne expliquant le programme. Facultatif.',
        'description_placeholder' => 'Gagnez des points à chaque visite et utilisez-les sur vos prochaines prestations.',

        'earn' => 'Gagner des points',
        'earn_hint' => 'Comment la dépense se transforme en points. Les points sont entiers : avec 5 € = 1 point, une prestation à 17 € en rapporte 3.',
        'spend_amount' => 'Dépensé',
        'points_earned' => 'Points',
        'earn_rule' => ':symbol:amount dépensés = :points',
        'eligible' => 'Achats éligibles',
        'eligible_hint' => 'Ce qui rapporte des points. Les prestations toujours — c’est ce dont une réservation est faite.',
        'always_on' => 'Toujours actif',
        'coming_soon' => 'Bientôt',

        'redeem' => 'Utiliser des points',
        'redeem_hint' => 'Ce qu’un point vaut en retour, et les limites pour dépenser un solde d’un coup.',
        'points_required' => 'Points requis',
        'reward_value' => 'Valeur de la récompense',
        'minimum_redemption' => 'Points minimum pour utiliser',
        'minimum_hint' => 'Le plus petit solde utilisable. En dessous, on indique au client ce qu’il lui reste à parcourir.',
        'maximum_reward' => 'Récompense maximale par transaction',
        'maximum_hint' => 'La remise maximale sur une seule visite. Laissez vide pour aucune limite.',
        'rule' => ':points points = :value',

        'expiry' => 'Expiration',
        'expiry_hint' => 'Combien de temps un point vit après avoir été gagné. Les points déjà gagnés conservent l’échéance qui leur a été donnée.',

        'notifications' => 'Notifications',
        'notifications_hint' => 'Ce qu’entend un client quand son solde bouge. Rien ne part encore — les messages arrivent avec la prochaine version.',

        'rules' => 'Règles de gestion',
        'rules_hint' => 'Les parties du programme que StyleDesk décide, pour que vous sachiez à quoi vous attendre.',
        'rule_locations' => 'Un seul solde pour toute l’entreprise',
        'rule_locations_body' => 'Un client gagne dans n’importe quel établissement et peut utiliser dans n’importe quel autre. Il n’y a pas de solde par succursale.',
        'rule_awarded' => 'Les points arrivent quand la visite est terminée et payée',
        'rule_awarded_body' => 'Brouillons, demandes, annulations, refus, absences et rendez-vous impayés ne rapportent rien. Un rendez-vous partiellement payé rapporte au prorata de ce qui a été réglé.',
        'rule_refunds' => 'Les remboursements reprennent les points',
        'rule_refunds_body' => 'Un remboursement total annule tout ce que la visite a rapporté. Un remboursement partiel en annule la même proportion, et le client garde le reste.',
        'rule_calculation' => 'Les points se comptent sur ce que le client a réellement payé',
        'rule_calculation_body' => 'Bons, remises et récompenses déjà utilisées sont déduits avant le calcul des points.',

        'saved' => 'Réglages de fidélité enregistrés.',
    ],

    /* ----------------------------------------------------- la fiche client -- */

    'client' => [
        'title' => 'Fidélité et récompenses',
        'off' => 'Les récompenses sont désactivées pour cette entreprise.',
        'off_hint' => 'Les soldes et l’historique sont conservés. Rien n’est gagné et rien ne peut être utilisé tant que le programme n’est pas réactivé.',

        'available' => 'Points disponibles',
        'available_hint' => 'Points utilisables dès maintenant.',
        'pending' => 'Points en attente',
        'pending_hint' => 'Attendus de rendez-vous pas encore terminés.',
        'lifetime_earned' => 'Total gagné',
        'lifetime_redeemed' => 'Total utilisé',

        'reward_value' => 'Valeur de la récompense',
        'worth' => 'Vaut :value',
        'worth_nothing' => 'Pas encore assez pour être utilisé',

        'next_reward' => 'Prochaine récompense',
        'progress' => ':have / :need points',
        'to_go' => 'Encore :points points pour débloquer :value',
        'unlocked' => ':value prêts à être utilisés',

        'activity' => 'Activité des récompenses',
        'none' => 'Aucune activité pour l’instant.',
        'none_filtered' => 'Rien dans cette partie de l’historique.',
        'columns' => [
            'date' => 'Date',
            'activity' => 'Activité',
            'booking' => 'Réservation',
            'location' => 'Établissement',
            'points' => 'Points',
            'balance' => 'Solde',
        ],
        'filters' => [
            'all' => 'Toute l’activité',
            'earned' => 'Gagnés',
            'redeemed' => 'Utilisés',
            'adjustments' => 'Ajustements',
            'expired' => 'Expirés',
            'refunds' => 'Remboursements',
        ],

        'adjust' => 'Ajuster les points',
        'adjust_title' => 'Ajuster les points',
        'adjust_intro' => 'Les points ajoutés ou retirés à la main sont enregistrés à votre nom et ne peuvent pas être modifiés ensuite.',
        'direction' => 'Type d’ajustement',
        'add' => 'Ajouter des points',
        'remove' => 'Retirer des points',
        'points' => 'Points',
        'reason' => 'Motif',
        'note' => 'Note interne',
        'note_hint' => 'Seule votre équipe la voit. Facultatif.',
        'save' => 'Enregistrer l’ajustement',
        'adjusted' => 'Points ajustés.',

        'by' => 'par :name',
        'expires' => 'Expire le :date',
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
