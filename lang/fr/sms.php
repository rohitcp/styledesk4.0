<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the desk reads about a text message: where it got to, and what it was
| about.
|
*/

return [
    'title' => 'SMS',

    'statuses' => [
        'queued' => ['label' => 'En file'],
        'sending' => ['label' => 'Envoi'],
        'sent' => ['label' => 'Envoyé'],
        'delivered' => ['label' => 'Remis'],
        'failed' => ['label' => 'Échec'],
        'rejected' => ['label' => 'Rejeté'],
        'expired' => ['label' => 'Expiré'],
        'opted_out' => ['label' => 'Désinscrit'],
    ],

    'types' => [
        'reply' => 'Réponse du client',
        'test' => 'Message de test',
        'booking_confirmation' => 'Confirmation de réservation',
        'appointment_reminder' => 'Rappel de rendez-vous',
        'booking_rescheduled' => 'Réservation reportée',
        'booking_cancelled' => 'Réservation annulée',
        'birthday' => 'Vœux d’anniversaire',
        'membership' => 'Notifications d’abonnement',
    ],

    'registration' => [
        'not_started' => 'Non commencé',
        'submitted' => 'Soumis',
        'pending' => 'En attente',
        'approved' => 'Approuvé',
        'rejected' => 'Rejeté',
        'suspended' => 'Suspendu',
    ],

    'settings' => [
        'sending_from' => 'Envoi depuis le :number',
        'test' => 'Envoyer un message de test',
        'test_hint' => 'Envoie un SMS par le même chemin qu’une confirmation de réservation, et l’enregistre comme n’importe quel autre.',
        'test_to' => 'Envoyer à',
        'send_test' => 'Envoyer un SMS',
        'test_body' => 'Message de test de :business. StyleDesk SMS fonctionne.',
        'test_sent' => 'Message de test envoyé au :number via :provider.',
        'test_failed' => 'Le message de test n’a pas pu être envoyé. :reason',
        'test_unknown' => 'Le fournisseur n’a donné aucune raison.',
        'test_bad_number' => 'Cela ne ressemble pas à un numéro de téléphone.',
        'test_live' => 'Telnyx est connecté : ce message arrivera sur un vrai téléphone et sera facturé.',
        'test_local' => 'Aucun opérateur n’est connecté : rien n’arrivera sur un téléphone, le message est seulement enregistré.',
        'title' => 'Paramètres SMS',
        'intro' => 'Ce que StyleDesk envoie par SMS à vos clients, depuis quel numéro, et ce que vous acceptez d’y dépenser.',
        'sender' => 'Numéro SMS',
        'sender_hint' => 'StyleDesk gère le numéro et son enregistrement auprès de l’opérateur pour vous. Rien ne peut partir vers un mobile américain tant que l’enregistrement n’est pas approuvé.',
        'no_number' => 'Pas encore attribué',
        'registration' => 'Enregistrement',
        'saved' => 'Paramètres SMS enregistrés.',
        'enable' => 'Activer StyleDesk SMS',
        'enable_hint' => 'Les SMS ne partent que lorsque ceci est activé.',
        'disabled_note' => 'Les SMS sont désactivés. Rien n’est envoyé et rien d’autre n’est demandé.',
        'messages' => 'Messages transactionnels',
        'messages_hint' => 'Quels SMS partent. Chacun n’est envoyé qu’aux clients qui l’ont accepté.',
        'not_yet' => 'Pas encore disponible.',
        'reminders' => 'Rappels de rendez-vous',
        'reminders_hint' => 'Combien de temps avant un rendez-vous part un rappel. Choisissez-en plusieurs pour en envoyer plusieurs.',
        'hours_before' => '{1} 1 heure avant|[2,*] :count heures avant',
        'birthday_at' => 'Envoyer les anniversaires à',
        'birthday_at_hint' => 'À l’heure locale de l’établissement.',
        'spend' => 'Utilisation et limites',
        'spend_hint' => 'Un plafond, pour qu’un import ou une erreur ne texte pas toute votre clientèle.',
        'monthly_limit' => 'Limite mensuelle de messages',
        'no_limit' => 'Sans limite',
        'alert_at' => 'M’alerter à',
        'used_this_month' => 'Messages ce mois-ci',
        'segments_this_month' => 'Segments ce mois-ci',
    ],

    'errors' => [
        'disabled' => 'StyleDesk SMS est désactivé sur cette installation.',
        'no_sender' => 'Aucun numéro d’envoi n’est défini. Ajoutez-en un dans Paramètres → SMS, ou définissez TELNYX_FROM_NUMBER.',
    ],

    'confirmation' => [
        'not_requested' => 'Non demandé',
        'pending' => 'En attente du client',
        'confirmed' => 'Confirmé par le client',
        'cancellation_requested' => 'Annulation demandée',
        'needs_review' => 'À vérifier',
    ],
];
