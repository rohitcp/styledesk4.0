<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Activité de l'entreprise
|--------------------------------------------------------------------------
|
| Le panneau derrière l'icône Activité de la barre d'application : qui a fait
| quoi, quand, et un chemin vers l'enregistrement concerné.
|
| Les types en un mot sont tout l'enjeu du vocabulaire ici. « Réservation »,
| « Annulation », « Paiement » — quelqu'un qui parcourt cette colonne doit
| trouver le genre de chose qu'il cherche sans lire une phrase, ce qui laisse
| la phrase du dessous libre de parler d'autre chose.
|
*/

return [

    'title' => 'Activité de l’entreprise',
    'intro' => 'Ce qui s’est passé dans l’entreprise.',
    'open' => 'Activité',
    'close' => 'Fermer l’activité',
    'mark_read' => 'Tout marquer comme lu',
    'unread' => ':count nouvelles',
    'unread_capped' => '99+ nouvelles',
    'loading' => 'Chargement…',
    'more' => 'Afficher plus',
    'empty' => 'Rien ne s’est encore passé.',
    'empty_hint' => 'Les réservations, paiements et changements dans l’entreprise apparaissent ici au fil de l’eau.',
    'empty_filtered' => 'Rien de ce genre pour l’instant.',

    'today' => 'Aujourd’hui',
    'yesterday' => 'Hier',
    'earlier' => 'Plus tôt',

    'by' => 'Par :name',
    'system' => 'StyleDesk',

    /*
    | Un mot chacun, jamais deux. Le type est une étiquette que l'on parcourt,
    | pas une phrase que l'on lit.
    */
    'kinds' => [
        'booking' => 'Réservation',
        'reschedule' => 'Report',
        'cancel' => 'Annulation',
        'checkin' => 'Arrivée',
        'checkout' => 'Départ',
        'noshow' => 'Absence',
        'client' => 'Client',
        'note' => 'Note',
        'file' => 'Fichier',
        'payment' => 'Paiement',
        'deposit' => 'Acompte',
        'refund' => 'Remboursement',
        'staff' => 'Équipe',
        'schedule' => 'Planning',
        'service' => 'Prestation',
        'resource' => 'Ressource',
        'email' => 'E-mail',
        'sms' => 'SMS',
        'review' => 'Avis',
        'coupon' => 'Bon',
        'giftcard' => 'Carte cadeau',
        'login' => 'Connexion',
        'settings' => 'Réglages',
    ],

    /* Les filtres en haut. */
    'groups' => [
        'all' => 'Tout',
        'bookings' => 'Réservations',
        'clients' => 'Clients',
        'payments' => 'Paiements',
        'staff' => 'Équipe',
        'scheduling' => 'Planification',
        'communication' => 'Communication',
        'system' => 'Système',
    ],

    /* Où mène la ligne quand on clique dessus. */
    'links' => [
        'booking' => 'Voir la réservation',
        'client' => 'Voir le client',
        'staff' => 'Voir le membre',
        'schedule' => 'Voir le planning',
        'service' => 'Voir la prestation',
        'resource' => 'Voir la ressource',
        'payment' => 'Voir le paiement',
    ],

    /*
    | Les phrases que cet écran écrit lui-même, pour les sources qui stockent
    | des faits plutôt que de la prose. Tout ce qui vient de la chronologie
    | client arrive déjà rédigé et s'affiche tel qu'il a été enregistré.
    */
    'sentences' => [
        'status' => 'La réservation :reference a été marquée :status.',
        'reason' => 'Motif : :reason.',
        'schedule' => 'Planning publié pour :name — :count services.',
    ],

    /*
    | Les changements administratifs, indexés par l'action enregistrée dans le
    | journal d'audit. Ce qui n'a pas d'entrée retombe sur les mots de l'action
    | elle-même plutôt que sur rien.
    */
    'audit' => [
        'fallback' => ':action — :name',
        'staff.created' => ':name a rejoint l’équipe.',
        'staff.edited' => 'Les informations de :name ont été mises à jour.',
        'staff.role_changed' => 'Le rôle de :name a été modifié.',
        'staff.deleted' => ':name a été retiré de l’équipe.',
        'staff.invitation_sent' => ':name a été invité à nous rejoindre.',
        'auth.login' => ':name s’est connecté.',
        'auth.logout' => ':name s’est déconnecté.',
        'auth.password_reset' => 'Le mot de passe de :name a été réinitialisé.',
    ],

];
