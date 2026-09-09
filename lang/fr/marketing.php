<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
|
| Les campagnes e-mail : ce que l'entreprise écrit à tout son fichier client,
| par opposition au module e-mail, qui est une conversation avec un seul client.
|
*/

return [

    'title' => 'Marketing par e-mail',
    'subtitle' => 'Campagnes vers votre fichier client',
    'intro' => 'Écrivez à vos clients en tant que groupe — offres, actualités et rappels — et voyez ce qu’ils en ont fait.',

    'create' => 'Créer une campagne e-mail',
    'edit' => 'Modifier la campagne',
    'saved' => 'Campagne enregistrée',
    'deleted' => 'Campagne supprimée',
    'delete' => 'Supprimer le brouillon',
    'delete_confirm' => 'Supprimer ce brouillon ? Il n’a été envoyé à personne, et l’action est irréversible.',
    'save_draft' => 'Enregistrer le brouillon',
    'cancel' => 'Annuler',
    'search' => 'Rechercher une campagne…',
    'all_statuses' => 'Tous les statuts',
    'filters_active' => 'Filtres',
    'clear' => 'Effacer',

    'summary' => [
        'campaigns' => 'Campagnes',
        'sent' => 'E-mails envoyés',
        'delivery_rate' => 'Taux de délivrabilité',
        'open_rate' => 'Taux d’ouverture',
        'click_rate' => 'Taux de clic',
        'unsubscribed' => 'Désabonnements',
    ],

    'statuses' => [
        'draft' => 'Brouillon',
        'scheduled' => 'Planifiée',
        'sending' => 'Envoi en cours',
        'sent' => 'Envoyée',
        'paused' => 'En pause',
        'cancelled' => 'Annulée',
        'failed' => 'Échec',
    ],

    'table' => [
        'name' => 'Campagne',
        'audience' => 'Audience',
        'recipients' => 'Destinataires',
        'scheduled' => 'Planifiée',
        'sent' => 'Envoyée',
        'delivered' => 'Délivrée',
        'opened' => 'Ouverte',
        'clicked' => 'Cliquée',
        'author' => 'Créée par',
        'status' => 'Statut',
    ],

    /* Étape un : ce qu'est l'e-mail et de qui il vient. */
    'details' => [
        'title' => 'Détails de la campagne',
        'intro' => 'Comment s’appelle cette campagne, et ce que vos clients verront dans leur boîte de réception.',
        'name' => 'Nom de la campagne',
        'name_hint' => 'Pour votre usage. Vos clients ne le voient jamais.',
        'name_placeholder' => 'Promotion massage de septembre',
        'subject' => 'Objet de l’e-mail',
        'subject_placeholder' => '20 % de remise sur votre prochain massage',
        'preview_text' => 'Texte d’aperçu',
        'preview_hint' => 'La ligne affichée après l’objet dans la plupart des boîtes de réception.',
        'preview_placeholder' => 'Réservez votre rendez-vous de septembre dès aujourd’hui.',
        'from_name' => 'Nom de l’expéditeur',
        'reply_to' => 'E-mail de réponse',
        'reply_hint' => 'Où vont les réponses. Laissez vide pour utiliser l’e-mail de votre entreprise.',
    ],

    /* Étape deux : à qui elle s'adresse. */
    'audience' => [
        'title' => 'Audience',
        'intro' => 'À qui va cette campagne. Les règles s’appliquent au moment de l’envoi, si bien qu’une liste qui grandit d’ici là grandit avec elle.',
        'scope' => 'Clients',
        'locations' => 'Établissements',
        'tags' => 'Étiquettes',
        'staff' => 'Vus par le praticien',
        'services' => 'Ayant eu la prestation',
        'lapsed' => 'N’est pas venu depuis',
        'visited' => 'Est venu dans les',
        'booking' => 'Rendez-vous à venir',
        'days' => ':days jours',
        'any' => 'Tous',
        'has_upcoming' => 'En a un de réservé',
        'no_upcoming' => 'N’a rien de réservé',

        'scopes' => [
            'all' => 'Tous les clients',
            'active' => 'Clients actifs',
        ],

        /*
        | La colonne audience de la liste, en toutes lettres.
        |
        | Leur propre groupe, parce que quatre d'entre elles entreraient sinon
        | en collision avec les libellés du formulaire ci-dessus — « N'est pas
        | venu depuis » est un libellé de champ et « pas de visite depuis
        | 90 jours » une phrase sur une campagne enregistrée ; la seconde
        | remplaçait silencieusement la première quand elles partageaient une clé.
        */
        'said' => [
            'locations' => '{1} 1 établissement|[2,*] :count établissements',
            'tags' => '{1} 1 étiquette|[2,*] :count étiquettes',
            'staff' => '{1} 1 praticien|[2,*] :count praticiens',
            'services' => '{1} 1 prestation|[2,*] :count prestations',
            'lapsed' => 'pas de visite depuis :days jours',
            'visited' => 'venu dans les :days jours',
            'has_upcoming' => 'a un rendez-vous à venir',
            'no_upcoming' => 'rien de réservé',
        ],
    ],

    /*
    | L'estimation. Trois nombres plutôt qu'un, parce que « 1 248 clients »
    | masque les deux faits qu'un propriétaire doit connaître avant d'envoyer.
    */
    'estimate' => [
        'title' => 'Destinataires estimés',
        'eligible' => 'Éligibles',
        'unsubscribed' => 'Désabonnés',
        'invalid' => 'E-mail invalide',
        'total' => 'Correspondant à vos règles',
        'counting' => 'Comptage…',
        'hint' => 'Compté maintenant. Les règles seront appliquées de nouveau à l’envoi, ce nombre peut donc bouger.',
        'none' => 'Personne ne correspond encore à ces règles.',
        'all_unsubscribed' => 'Toutes les personnes correspondant à ces règles se sont désabonnées des e-mails marketing.',
    ],

    /* Ce qui est construit, et ce qui ne l'est pas. */
    'next' => [
        'title' => 'Encore à venir',
        'intro' => 'Cette campagne peut être décrite et enregistrée. La concevoir et l’envoyer sont les étapes suivantes.',
        'design' => 'Concevoir l’e-mail',
        'preview' => 'Aperçu et test',
        'send' => 'Envoyer ou planifier',
        'soon' => 'Bientôt',
    ],

    'empty' => 'Aucune campagne pour l’instant.',
    'empty_hint' => 'Créez-en une pour écrire à vos clients en tant que groupe.',
    'no_matches' => 'Aucune campagne ne correspond à cette recherche.',

    'results' => [
        'zero' => 'Aucune campagne trouvée',
        'one' => '1 campagne trouvée',
        'many' => ':count campagnes trouvées',
    ],
    'showing' => 'Affichage de :from à :to sur :total campagnes',
    'actions_for' => 'Actions pour :name',

];
