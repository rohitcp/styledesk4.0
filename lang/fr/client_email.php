<?php

declare(strict_types=1);

return [

    'title' => 'E-mail',
    'intro' => 'Envoyez des e-mails à vos clients depuis StyleDesk.',

    'settings' => [
        'enable' => 'Activer l’e-mail client',
        'enable_hint' => 'Quand c’est désactivé, « Envoyer un e-mail » disparaît des fiches clients. L’historique reste où il est.',
        'enabled' => 'Activé',
        'disabled' => 'Désactivé',

        'default_method' => 'Méthode d’envoi par défaut',
        'default_hint' => 'Un seul prestataire envoie vos e-mails clients. Vous pouvez en changer à tout moment.',
        'active' => 'ACTIF',
        'coming_soon' => 'Bientôt',
        'use_this' => 'Utiliser celui-ci',

        'sender' => 'Expéditeur',
        'sender_hint' => 'Comment vos e-mails sont signés, quelle que soit la méthode d’envoi.',
        'sender_name' => 'Nom de l’expéditeur',
        'sender_name_hint' => 'Le nom que voient vos clients. Par défaut, le nom de votre entreprise.',
        'reply_to' => 'E-mail de réponse',
        'reply_to_hint' => 'Où va la réponse d’un client. Sans cela, les réponses n’atteignent personne.',
        'reply_to_gmail' => 'Non utilisé tant que Gmail envoie — les réponses arrivent directement dans votre boîte connectée.',
        'preview' => 'Vos clients verront',

        'send_test' => 'Envoyer un e-mail de test',
        'test_hint' => 'Envoyé à votre propre adresse, pour voir exactement ce que reçoit un client.',
        'test_sent' => 'E-mail de test envoyé à :email.',
        'test_failed' => 'Le test n’a pas pu être envoyé. :reason',
        'test_subject' => 'E-mail de test depuis StyleDesk',
        'test_body' => "Ceci est un e-mail de test envoyé par :name.\n\nSi vous lisez ce message, votre e-mail client est configuré et fonctionne. Rien n’a été envoyé à vos clients.",

        'saved' => 'Vos réglages d’e-mail ont été enregistrés.',
        'save' => 'Enregistrer',
    ],

    'providers' => [
        'styledesk' => [
            'name' => 'E-mail StyleDesk',
            'description' => 'Envoyez vos e-mails directement via StyleDesk, sans connecter de compte externe.',
        ],
        'gmail' => [
            'name' => 'Connecter Gmail',
            'description' => 'Connectez votre compte Gmail professionnel ou Google Workspace et envoyez depuis votre adresse existante.',
        ],
    ],

    'connection' => [
        'connected' => 'Connecté',
        'disconnected' => 'Déconnecté',
        'needs_attention' => 'À vérifier',
    ],

    'send' => [
        'action' => 'Envoyer un e-mail',
        'title' => 'Envoyer un e-mail',
        'to' => 'À',
        'from' => 'De',
        /* Ce que le client voit réellement dans sa boîte. Dit clairement sur
           l'écran de réglages aussi, parce qu'un propriétaire qui attendait sa
           propre adresse doit l'apprendre ici plutôt que d'un client. */
        'from_via' => ':name via StyleDesk',
        'template' => 'Modèle d’e-mail',
        'no_template' => 'Aucun modèle',
        'related_booking' => 'Réservation liée',
        'no_booking' => 'Aucune',
        'subject' => 'Objet',
        'message' => 'Message',
        'cancel' => 'Annuler',
        'submit' => 'Envoyer l’e-mail',
        'sending' => 'Envoi…',
        'sent' => 'E-mail envoyé à :name',
    ],

    'statuses' => [
        'queued' => 'En file d’attente',
        'sent' => 'Envoyé',
        'delivered' => 'Délivré',
        'failed' => 'Échec',
    ],

    'history' => [
        'title' => 'Historique des e-mails',
        'empty' => 'Aucun e-mail n’a encore été envoyé à ce client.',
        'sent_by' => 'Envoyé par :name',
        'system' => 'Système StyleDesk',
        'view' => 'Voir',
    ],

    'errors' => [
        'disabled' => 'L’e-mail client est désactivé pour cette entreprise. Activez-le dans Réglages → E-mail.',
        'no_provider' => 'Aucune méthode d’envoi n’est configurée. Choisissez-en une dans Réglages → E-mail.',
        'no_address' => 'Ce client n’a pas d’adresse e-mail au dossier.',
        'failed' => 'L’e-mail n’a pas pu être envoyé. Il figure sur la fiche du client comme échoué, et vous pouvez réessayer.',
        'gmail_not_connected' => 'Aucun compte Gmail n’est connecté. Connectez-en un dans Réglages → E-mail.',
        'reconnect_gmail' => 'Reconnecter Gmail',
    ],

    /*
    | Les modèles dont un message peut partir.
    |
    | Des échafaudages, pas des enveloppes : le panneau en dépose un, l'expéditeur
    | le modifie, et ce qui est enregistré est ce qui a réellement été envoyé.
    */
    'templates' => [
        'appointment_follow_up' => [
            'name' => 'Suivi de rendez-vous',
            'subject' => 'Merci de votre visite chez {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\nMerci d’être venu le {{booking_date}}. Nous espérons que votre {{service_name}} vous plaît.\n\nS’il y a quoi que ce soit à ajuster, répondez simplement à cet e-mail et nous nous en occupons.\n\nMerci,\n{{business_name}}",
        ],
        'appointment_information' => [
            'name' => 'Informations sur le rendez-vous',
            'subject' => 'Votre rendez-vous chez {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\nVoici un mot au sujet de votre rendez-vous du {{booking_date}} à {{booking_time}} avec {{staff_name}}.\n\nRéférence : {{booking_reference}}\n\nSi vous devez changer quelque chose, répondez à cet e-mail ou appelez-nous.\n\nMerci,\n{{business_name}}",
        ],
        'payment_reminder' => [
            'name' => 'Rappel de paiement',
            'subject' => 'Un rappel concernant votre solde chez {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\nUn petit rappel : votre solde restant est de {{balance_due}}.\n\nVous pouvez le régler lors de votre prochaine visite, ou répondre à cet e-mail et nous vous enverrons un lien de paiement.\n\nMerci,\n{{business_name}}",
        ],
        'outstanding_balance' => [
            'name' => 'Solde restant dû',
            'subject' => 'Solde restant pour votre visite du {{booking_date}}',
            'body' => "Bonjour {{client_first_name}},\n\nVotre visite du {{booking_date}} présente un solde restant de {{balance_due}}.\n\nRéférence : {{booking_reference}}\n\nSi vous pensez qu’il y a une erreur, répondez-nous et nous vérifierons tout de suite.\n\nMerci,\n{{business_name}}",
        ],
        'thank_you' => [
            'name' => 'Remerciement',
            'subject' => 'Merci de la part de {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\nMerci d’avoir choisi {{business_name}}. Ce fut un plaisir de vous recevoir.\n\nAu plaisir de vous revoir bientôt.\n\nMerci,\n{{business_name}}",
        ],
        'service_follow_up' => [
            'name' => 'Suivi de prestation',
            'subject' => 'Comment se porte votre {{service_name}} ?',
            'body' => "Bonjour {{client_first_name}},\n\nCela fait un petit moment depuis votre {{service_name}} avec {{staff_name}}. Nous voulions savoir comment cela se passe.\n\nSi vous souhaitez une retouche ou avez des questions, répondez simplement à cet e-mail.\n\nMerci,\n{{business_name}}",
        ],
        'membership_information' => [
            'name' => 'Informations sur les abonnements',
            'subject' => 'Les abonnements chez {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\nNous avons pensé que nos formules d’abonnement pourraient vous intéresser : elles offrent aux clients réguliers de meilleurs tarifs et une réservation prioritaire.\n\nRépondez à cet e-mail et nous vous enverrons les détails.\n\nMerci,\n{{business_name}}",
        ],
        'general_message' => [
            'name' => 'Message général',
            'subject' => 'Un message de {{business_name}}',
            'body' => "Bonjour {{client_first_name}},\n\n\nMerci,\n{{business_name}}",
        ],
    ],

    'gmail' => [
        'connect' => 'Connecter Gmail',
        'reconnect' => 'Reconnecter',
        'disconnect' => 'Déconnecter',
        'connected' => 'Gmail connecté. Vos e-mails clients partent désormais de :email.',
        'connected_on' => 'Connecté le :date',
        'disconnected' => 'Gmail a été déconnecté. Les e-mails clients passent de nouveau par l’e-mail StyleDesk.',
        'failed' => 'Gmail n’a pas pu être connecté. :reason',
        'state_mismatch' => 'Cette tentative de connexion n’a pas pu être vérifiée. Réessayez.',
        'no_code' => 'Google n’a rien renvoyé permettant de se connecter.',
        'no_refresh_token' => 'Google n’a pas délivré d’autorisation durable pour ce compte.',
        'reconnect_needed' => 'La connexion Gmail ne fonctionne plus et doit être rétablie.',
        'unknown_error' => 'Google n’a pas dit pourquoi.',
        'replies_note' => 'Les clients répondent directement dans cette boîte. Les réponses ne sont pas rapatriées dans StyleDesk.',
    ],

    'variables' => [
        'title' => 'Vous pouvez utiliser',
        'hint' => 'Elles sont remplacées au chargement du modèle.',
    ],
];
