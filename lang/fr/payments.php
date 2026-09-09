<?php

declare(strict_types=1);

return [

    'title' => 'Paiements',
    'intro' => 'Si vous encaissez, qui traite les paiements, et ce que vous acceptez.',

    'enable' => 'Activer les paiements',
    'enable_hint' => 'Lorsque c’est désactivé, StyleDesk n’enregistre aucun montant sur les réservations et l’encaissement est masqué.',

    'processor' => 'Prestataire de paiement',
    'processor_hint' => 'Un seul prestataire traite vos paiements par carte. Quel que soit votre choix, vous pouvez toujours enregistrer les espèces et les virements.',
    'active' => 'ACTIF',
    'coming_soon' => 'Bientôt',
    'use_this' => 'Utiliser celui-ci',

    'gateways' => [
        'manual' => [
            'name' => 'Enregistrement des paiements uniquement',
            'description' => 'L’argent qui arrive autrement — espèces, virement, ou une carte encaissée sur votre propre terminal. StyleDesk note qu’il est arrivé ; il ne débite personne.',
            'unavailable' => '',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Encaissez cartes, Apple Pay et Google Pay en ligne et au comptoir, avec des versements sur votre propre compte bancaire.',
            'unavailable' => '',
        ],
        'square' => [
            'name' => 'Square',
            'description' => 'Connectez le compte Square que vous utilisez déjà, avec vos lecteurs et terminaux existants.',
            'unavailable' => 'Pas encore disponible. StyleDesk y travaille.',
        ],
    ],

    /* Card brands, as a person writes them rather than as a gateway keys
       them. Anything not listed falls back to its own key, tidied up — a new
       brand should read as itself rather than as nothing. */
    'methods_list' => [
        'no_vault' => 'Aucun prestataire de paiement n’est connecté, les cartes ne peuvent pas être enregistrées.',
        'default_set' => ':card est désormais le moyen de paiement par défaut.',
        'removed' => ':card a été retirée.',
        'title' => 'Moyens de paiement',
        'none' => 'Aucune carte enregistrée',
        'none_hint' => 'Une carte enregistrée ici peut être débitée pour les renouvellements d’abonnement sans le client.',
        'default' => 'Par défaut',
        'make_default' => 'Définir par défaut',
        'expires' => 'Expire le :date',
        'expired' => 'Expirée',
        'expiring' => 'Expire ce mois-ci',
        'needs_attention' => 'Le moyen de paiement demande votre attention',
        'add' => 'Ajouter une carte',
        'remove' => 'Retirer la carte',
        'remove_confirm' => 'Retirer cette carte ? Elle ne pourra plus être débitée.',
        'in_use' => 'Cette carte renouvelle :name. Choisissez un autre moyen de paiement avant de la retirer.',
        'used_by' => 'Renouvelle :name',
        'gateway' => 'Traité par :name',
        'statuses' => [
            'active' => 'Active',
            'expired' => 'Expirée',
            'removed' => 'Retirée',
        ],
    ],

    'brands' => [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'discover' => 'Discover',
        'diners' => 'Diners Club',
        'jcb' => 'JCB',
        'unionpay' => 'UnionPay',
    ],

    'stripe' => [
        'not_settled' => 'La carte n’a pas été débitée. Le paiement n’a pas abouti.',
        'connect' => 'Connecter Stripe',
        'continue' => 'Poursuivre la configuration',
        'manage' => 'Gérer le compte',
        'disconnect' => 'Déconnecter',
        'not_connected' => 'Aucun compte Stripe connecté pour l’instant.',
        'no_account' => 'Il n’y a aucun compte Stripe à ouvrir.',
        'payout_account' => 'Versements vers •••• :last4',
        'no_payout_account' => 'Aucun compte bancaire de versement pour l’instant',
        'connected' => 'Stripe est connecté. Vous pouvez désormais encaisser par carte.',
        'still_needed' => 'Stripe a encore besoin de quelques informations avant que vous puissiez encaisser.',
        'disconnected' => 'Stripe a été déconnecté. Vous pouvez toujours enregistrer les espèces et les virements.',
        'failed' => 'Stripe est injoignable. :reason',
        'not_ready' => 'Cet établissement ne peut pas encore encaisser par carte.',
        'outstanding' => 'Stripe attend encore : :fields',
        'refunded_at_stripe' => 'Remboursé depuis le tableau de bord Stripe.',
        'modes' => [
            'platform' => 'Connecté via StyleDesk',
            'own' => 'Votre propre compte Stripe',
        ],
        'use_own' => 'Utiliser plutôt mon propre compte Stripe',
        'replace_keys' => 'Remplacer mes clés Stripe',
        'use_own_hint' => 'Vous avez déjà Stripe ? Collez vos clés et StyleDesk utilisera directement votre compte. Paiements, versements et litiges restent entièrement entre Stripe et vous.',
        'secret_key' => 'Clé secrète',
        'publishable_key' => 'Clé publiable',
        'save_keys' => 'Enregistrer et vérifier',
        'keys_saved' => 'Vos clés Stripe ont été enregistrées et vérifiées.',
        'key_rejected' => 'Stripe a refusé cette clé. :reason',
        'key_empty' => 'Aucune clé n’a été fournie.',
        'key_warning' => 'Une clé secrète permet de débiter, rembourser et tout lire sur votre compte Stripe. StyleDesk la chiffre et ne l’affiche plus jamais — traitez-la comme un mot de passe, et utilisez une clé restreinte si vous préférez limiter ce que StyleDesk peut faire.',
        'platform_not_configured' => 'StyleDesk n’est pas configuré pour créer des comptes Stripe.',
        'platform_unavailable' => 'La connexion via StyleDesk n’est pas disponible sur cette installation. Vous pouvez tout de même utiliser votre propre compte Stripe ci-dessous.',
        'statuses' => [
            'connected' => 'Connecté',
            'needs_attention' => 'À vérifier',
            'incomplete' => 'Configuration incomplète',
        ],
        /* Dit une seule fois, parce que c'est ce qu'un propriétaire veut le
           plus savoir, et la raison pour laquelle Connect a été choisi plutôt
           qu'un compte marchand partagé. */
        'money_note' => 'Les paiements vont directement sur votre propre compte Stripe et votre propre banque. StyleDesk ne détient jamais votre argent.',
    ],

    'methods' => 'Moyens de paiement acceptés',
    'methods_hint' => 'Ce que votre équipe peut choisir en caisse. Certains sont traités par votre prestataire de carte ; les autres sont encaissés autrement et enregistrés ici.',
    'needs_processor' => 'Nécessite un prestataire de carte connecté',

    'method_names' => [
        'card' => 'Carte bancaire',
        'cash' => 'Espèces',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'gift_card' => 'Carte cadeau',
        'store_credit' => 'Avoir',
        'paypal' => 'PayPal',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'cash-app' => 'Cash App',
        'external' => 'Autre paiement / externe',
    ],

    'deposit' => 'Acompte de réservation',
    'deposit_hint' => 'Ce que vous demandez d’avance lorsqu’une réservation est prise.',
    'deposit_type' => 'Acompte',
    'deposit_value' => 'Montant',
    'deposit_types' => [
        'none' => 'Aucun acompte',
        'fixed' => 'Montant fixe',
        'percent' => 'Pourcentage de la réservation',
    ],
    /* Les trois niveaux, dits une fois. Une prestation qui demande son propre
       acompte et une réservation qui écrase les deux sont les deux autres, et
       un propriétaire qui ignore l'ordre ne comprendra pas pourquoi une
       prestation ignore ceci. */
    'deposit_levels' => 'C’est la valeur par défaut. Une prestation peut demander le sien, et une réservation individuelle peut écraser les deux.',

    'save' => 'Enregistrer',
    'saved' => 'Vos réglages de paiement ont été enregistrés.',
];
