<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Codes de motif
|--------------------------------------------------------------------------
|
| Pourquoi quelque chose s'est produit, choisi dans une liste plutôt que
| saisi. Les listes elles-mêmes vivent dans config/reasons.php ; ceci est
| leur nom.
|
*/

return [

    'title' => 'Motifs',
    'intro' => 'Pourquoi les choses se sont produites, sous forme de liste plutôt que de champ libre — pour que « pourquoi perdons-nous des réservations » soit une question à laquelle les rapports peuvent vraiment répondre.',
    'back' => 'Toutes les listes de motifs',

    'types' => [
        'booking-cancellation' => ['label' => 'Annulation de réservation', 'intro' => 'Pourquoi un rendez-vous a été annulé.'],
        'booking-reschedule' => ['label' => 'Report de réservation', 'intro' => 'Pourquoi un rendez-vous a été déplacé.'],
        'no-show' => ['label' => 'Absence', 'intro' => 'Pourquoi quelqu’un n’est pas venu.'],
        'refund' => ['label' => 'Remboursement', 'intro' => 'Pourquoi de l’argent est ressorti.'],
        'payment-adjustment' => ['label' => 'Ajustement de paiement', 'intro' => 'Pourquoi une note a été modifiée après avoir été établie.'],
        'client-status-change' => ['label' => 'Changement de statut client', 'intro' => 'Pourquoi un client est passé d’actif à inactif ou autre.'],
        'staff-schedule-change' => ['label' => 'Changement de planning', 'intro' => 'Pourquoi un planning a changé — services, heures, congés et remplacements.'],
        'booking-declined' => ['label' => 'Réservation refusée', 'intro' => 'Pourquoi une demande de rendez-vous a été refusée.'],
        'service-cancellation' => ['label' => 'Retrait d’une prestation', 'intro' => 'Pourquoi une prestation n’est plus proposée.'],
    ],

    'columns' => [
        'reason' => 'Motif',
        'source' => 'Source',
        'details' => 'Demande pourquoi',
        'status' => 'Statut',
        'action' => 'Action',
    ],

    'system' => 'StyleDesk',
    'custom' => 'Les vôtres',
    'renamed' => 'Renommé',
    'active' => 'Activé',
    'inactive' => 'Désactivé',
    'activate' => 'Activer',
    'deactivate' => 'Désactiver',
    'edit' => 'Modifier',
    'delete' => 'Supprimer le motif',
    'delete_confirm' => 'Supprimer « :name » ? Tout ce qui a déjà été enregistré sous ce motif le conserve ; plus rien de nouveau ne pourra y être classé.',
    'system_undeletable' => 'Celui-ci est fourni par StyleDesk. Il peut être renommé ou désactivé, mais pas supprimé — les enregistrements classés dessous doivent toujours dire pourquoi.',

    'add' => 'Ajouter un motif',
    'add_title' => 'Ajouter un motif',
    'edit_title' => 'Modifier le motif',
    'name' => 'Motif',
    'name_placeholder' => 'Le client a changé d’avis',
    'description' => 'Description',
    'description_hint' => 'Facultatif. Ce que celui-ci veut dire, pour la prochaine personne qui le choisira.',
    'requires_details' => 'Demander une explication quand celui-ci est choisi',
    'requires_details_hint' => 'Pour les motifs qui ne sont pas une réponse à eux seuls — « Autre » est le cas évident.',
    'details_label' => 'Précisions',

    'extra_title' => 'Également demandé',
    'extra_hint' => 'Cette liste pose une seconde question en plus du motif. Elle fait partie de la façon dont :label est enregistré et n’est pas configurable.',

    'counts' => ':active activés sur :total',
    'reorder_hint' => 'Faites glisser pour réordonner',
    'save_order' => 'Enregistrer l’ordre',
    'order_changed' => 'L’ordre a changé mais n’a pas encore été enregistré.',

    'added' => 'Motif ajouté.',
    'updated' => 'Motif mis à jour.',
    'activated' => 'Motif activé.',
    'deactivated' => 'Motif désactivé. Tout ce qui a déjà été enregistré dessous reste intact.',
    'deleted' => 'Motif supprimé.',
    'order_saved' => 'Ordre enregistré.',
    'none' => 'Aucun motif dans cette liste pour l’instant.',
];
