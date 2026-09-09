<?php

declare(strict_types=1);

/*
| Des réservations commencées et jamais terminées.
|
| Le vocabulaire traite une demande comme un appel à rendre, pas comme un
| échec. La plupart sont des gens qui ont raccroché pour vérifier une date, et
| un écran qui les qualifierait d'« abandonnées » ferait s'excuser l'accueil
| pour son propre agenda.
*/

return [

    'title' => 'Demandes de réservation',
    'intro' => 'Des réservations commencées mais jamais terminées. Chacune est un appel qui mérite d’être rendu.',

    'search' => 'Rechercher par nom, référence ou numéro…',
    'search_label' => 'Rechercher une demande',
    'all_statuses' => 'Tous les statuts',

    'actions' => [
        'complete' => 'Poursuivre la réservation',
        'view_booking' => 'Voir la réservation',
        'view_client' => 'Voir le client',
    ],

    'columns' => [
        'who' => 'Client',
        'services' => 'Demandé',
        'expected' => 'Souhaité pour',
        'location' => 'Établissement',
        'total' => 'Valeur',
        'started' => 'Commencée',
        'step' => 'Arrêtée à',
        'status' => 'Statut',
    ],

    /*
    | Ce qu'est devenue une demande. Pas jusqu'où elle est allée — c'est
    | l'étape ci-dessous, et les deux sont séparées à dessein : « relance
    | nécessaire » dit d'appeler, « acompte et paiement » dit de quoi parler.
    */
    'statuses' => [
        'draft' => ['label' => 'Brouillon'],
        'new' => ['label' => 'Nouvelle'],
        'in-progress' => ['label' => 'En cours'],
        'awaiting-confirmation' => ['label' => 'En attente du client'],
        'awaiting-deposit' => ['label' => 'En attente d’acompte'],
        'payment-pending' => ['label' => 'Paiement en attente'],
        'follow-up' => ['label' => 'Relance nécessaire'],
        'contacted' => ['label' => 'Contacté'],
        'converted' => ['label' => 'Convertie'],
        'abandoned' => ['label' => 'Abandonnée'],
        'cancelled' => ['label' => 'Annulée'],
        'lost' => ['label' => 'Perdue'],
        'expired' => ['label' => 'Expirée'],
    ],

    /* Jusqu'où le client est allé dans l'écran de réservation. */
    'steps' => [
        'service' => 'Prestation',
        'when' => 'Avec qui et quand',
        'details' => 'Détails de la réservation',
        'payment' => 'Acompte et paiement',
        'comms' => 'Communication',
        'completed' => 'Terminée',
    ],

    /* Pourquoi elle s'est arrêtée là. */
    'reasons' => [
        'changed-mind' => 'Le client a changé d’avis',
        'no-suitable-time' => 'Aucun créneau convenable',
        'staff-unavailable' => 'Praticien préféré indisponible',
        'price' => 'Prix',
        'duplicate' => 'Demande en double',
        'declined' => 'Le client a refusé',
        'unreachable' => 'Impossible à joindre',
        'booked-elsewhere' => 'A réservé ailleurs',
        'no-availability' => 'Aucune disponibilité convenable',
        'other' => 'Autre',
    ],

    'contact_methods' => [
        'phone' => 'Téléphone',
        'sms' => 'SMS',
        'email' => 'E-mail',
        'whatsapp' => 'WhatsApp',
        'in-person' => 'En personne',
    ],

    /*
    | Le panneau que la liste ouvre par-dessus elle-même.
    |
    | Un champ que personne n'a encore rempli affiche « Non renseigné » plutôt
    | que de revenir vide : une ligne blanche se lit comme une panne, et ce qui
    | manque est en général l'objet même de l'appel.
    */
    'drawer' => [
        'not_selected' => 'Non renseigné',
        'summary' => 'Demande',
        'created' => 'Créée',
        'created_by' => 'Créée par',
        'last_activity' => 'Dernière activité',
        'taken_by' => 'Dernier contact par',
        'client' => 'Client',
        'name' => 'Nom',
        'phone' => 'Téléphone',
        'email' => 'E-mail',
        'preferences' => 'Préférences de réservation',
        'booking' => 'Détails de la réservation',
        'services' => 'Prestations',
        'duration' => 'Durée',
        'price' => 'Prix',
        'date' => 'Date',
        'time' => 'Heure',
        'staff' => 'Avec',
        'location' => 'Établissement',
        'notes' => 'Notes',
        'payment' => 'Paiement',
        'estimated_total' => 'Total estimé',
        'deposit_required' => 'Acompte exigé',
        'deposit_paid' => 'Acompte versé',
        'outstanding' => 'Reste à payer',
        'payment_status' => 'Statut du paiement',
        'journey' => 'Avancement de la réservation',
        'activity' => 'Activité',
        'no_activity' => 'Rien d’enregistré pour l’instant.',
        'stopped_at' => 'Arrêtée à',
        'view_client' => 'Ouvrir la fiche client',
        'close' => 'Fermer',
        'send_email' => 'Envoyer un e-mail',
        'send_sms' => 'Envoyer un SMS',
        'soon' => 'Bientôt',
    ],

    'events' => [
        'created' => 'Demande de réservation créée',
        'step' => ':step terminée',
        'cancelled' => 'Annulée — :reason',
        'converted' => 'Convertie en réservation',
        'contacted' => 'Client contacté',
    ],

    'notes' => [
        'button' => 'Notes',
        'title' => 'Notes',
        'write' => 'Ajouter une note',
        'placeholder' => 'Appelé à 16 h, pas de réponse — je réessaie demain.',
        'hint' => 'Enregistrée sur la fiche client et rattachée à cette demande, pour que la personne suivante la voie des deux côtés.',
        'save' => 'Enregistrer la note',
        'saved' => 'Note enregistrée sur la fiche client.',
        'empty' => 'Aucune note sur cette demande pour l’instant.',
        'needs_client' => 'Un client sans rendez-vous n’a pas de fiche sur laquelle garder une note.',
    ],

    'cancel' => [
        'title' => 'Annuler cette demande de réservation ?',
        'body' => 'La demande est marquée annulée et conservée dans l’historique, avec qui l’a annulée et quand.',
        'reason' => 'Motif',
        'choose_reason' => 'Choisissez un motif',
        'note' => 'Note',
        'note_placeholder' => 'Ce que la personne suivante devrait savoir — facultatif.',
        'keep' => 'Conserver la demande',
        'confirm' => 'Annuler la demande',
        'cancelled' => 'Demande de réservation annulée.',
        'failed' => 'L’enregistrement a échoué. Réessayez.',
    ],

    'empty' => 'Aucune demande ne correspond à ces filtres.',
    'none_yet' => 'Aucune demande de réservation pour l’instant',
    'none_yet_hint' => 'Une demande est enregistrée quand quelqu’un commence une réservation sans la terminer, pour que vous ayez la référence et les prestations sur lesquelles rappeler.',

    'showing' => 'Affichage de :from à :to sur :total demandes',
    'results' => [
        'zero' => 'Aucune demande',
        'one' => '1 demande',
        'many' => ':count demandes',
    ],
];
