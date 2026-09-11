<?php

declare(strict_types=1);

/*
| Les rendez-vous.
|
| Le vocabulaire garde séparées deux choses faciles à confondre : la note de
| rendez-vous porte sur cette réservation-ci, la note client porte sur la
| personne. L'une se lit une fois le jour même ; l'autre est lue par tous ceux
| qui la réservent, aussi longtemps qu'elle est cliente.
*/

return [

    'title' => 'Réservations',
    'search_placeholder' => 'Rechercher réservations, clients, numéro…',
    'intro' => 'Chaque rendez-vous pris, et avec qui.',
    'new' => 'Nouvelle réservation',
    'new_intro' => 'Trouvez le client, ou prenez un sans rendez-vous.',
    'walk_in_intro' => 'Notez les informations telles qu’elles arrivent au comptoir.',

    'add' => [
        'client' => 'Ajouter un client',
        'booking' => 'Ajouter une réservation',
        'walk_in' => 'Ajouter une réservation · sans rendez-vous',
        'leave' => 'Ajouter une absence',
    ],

    'modes' => [
        'booking' => 'Réservation',
        'walkin' => 'Sans rendez-vous',
    ],

    'any_staff' => 'N’importe qui de disponible',
    'walk_in_guest' => 'Sans rendez-vous',

    'columns' => [
        'arrival' => 'Arrivée',
        'checkin' => 'Enregistrement',
        'location' => 'Établissement',
        'reference' => 'N° de réservation',
        'booked_by' => 'Réservé par',
        'client' => 'Client',
        'date' => 'Date',
        'time' => 'Heure',
        'services' => 'Prestations',
        'staff' => 'Avec',
        'total' => 'Total',
        'status' => 'Statut',
    ],

    'filters' => [
        'all_statuses' => 'Tous les statuts',
        'all_locations' => 'Tous les établissements',
        'all_services' => 'Toutes les prestations',
        'all_payments' => 'Tous les statuts de paiement',
        'all_staff' => 'Toute l’équipe',
        'date' => 'Date',
        'reset' => 'Réinitialiser',
    ],

    'statuses' => [
        'pending' => ['label' => 'En attente'],
        'draft' => ['label' => 'Brouillon'],
        'confirmed' => ['label' => 'Confirmée'],
        'arrived' => ['label' => 'Arrivé'],
        'completed' => ['label' => 'Terminée'],
        'no-show' => ['label' => 'Absence'],
        'declined' => ['label' => 'Refusée'],
        'cancelled' => ['label' => 'Annulée'],
    ],

    'sources' => [
        'front-desk' => 'Accueil',
        'phone' => 'Téléphone',
        'online' => 'En ligne',
        'walk-in' => 'Sans rendez-vous',
        'social' => 'Réseaux sociaux',
        'referral' => 'Recommandation',
        'other' => 'Autre',
    ],

    /*
    | Les cinq décisions que porte l'écran de réservation, dans l'ordre où on
    | les dit à voix haute plutôt que dans celui qu'une base de données
    | voudrait.
    */
    'sections' => [
        'purchase' => 'Choisir le type',
        'client' => 'Client',
        'service' => 'Prestation',
        'when' => 'Praticien et horaire',
        'details' => 'Détails de la réservation',
        'payment' => 'Acompte / paiement',
        'comms' => 'Communication',
        'summary' => 'Récapitulatif',
    ],

    'purchase' => [
        'title' => 'Choisir le type',
        'question' => 'Que souhaitez-vous vendre ?',
        'services' => 'Prestations',
        'services_hint' => 'Un rendez-vous : prestations, équipe, horaire et note.',
        'membership' => 'Abonnement',
        'membership_hint' => 'Un abonnement récurrent ou un forfait de prestations.',
        'gift_card' => 'Carte cadeau',
        'gift_card_hint' => 'Un solde que le client pourra dépenser plus tard.',
        'coming_soon' => 'Bientôt disponible',
        'membership_off' => 'Activez d’abord les abonnements dans les Paramètres.',
        'membership_empty' => 'Publiez d’abord un abonnement dans Clients → Abonnement.',
    ],

    'membership' => [
        'select' => 'Choisir l’abonnement',
        'plans' => 'Formules d’abonnement',
        'packages' => 'Forfaits d’abonnement',
        'none' => 'Rien de publié à vendre pour l’instant.',
        'none_hint' => 'Publiez un abonnement dans Clients → Abonnement et il apparaîtra ici.',
        'includes' => 'Comprend',
        'select_this' => 'Choisir cet abonnement',
        'selected' => 'Choisi',
        'change' => 'Changer',
        'saving' => 'Le client économise :amount',
        'trial' => 'Essai de :days jours',
        'joining_fee' => 'Frais d’adhésion :amount',
        'setup_fee' => 'Frais de mise en service :amount',
        'start' => 'Date de début',
        'start_today' => 'Commencer aujourd’hui',
        'start_later' => 'Choisir une date de début',
        'start_locked' => 'Les abonnements commencent le jour de leur vente.',
        'scheduled_note' => 'Cet abonnement sera Programmé jusqu’au :date.',
        'purchase_summary' => 'Récapitulatif de l’achat',
        'billing' => 'Facturation',
        'next_billing' => 'Prochain prélèvement',
        'one_off' => 'Achat unique',
        'due_today' => 'Montant dû aujourd’hui',
        'client_required' => 'Choisissez d’abord un client : un abonnement est toujours rattaché à quelqu’un.',
        'plan_required' => 'Choisissez un abonnement.',
        'method_note' => 'Un abonnement récurrent exige un moyen de paiement rechargeable.',
        'complete' => 'Finaliser l’achat',
    ],

    'credits' => [
        'available' => 'Avantage abonnement disponible',
        'from' => 'De :name',
        'remaining' => ':count disponibles',
        'apply' => 'Utiliser un crédit d’abonnement',
        'apply_many' => 'Utiliser l’abonnement · :count crédits',
        'applied_many' => 'Abonnement · :count crédits utilisés',
        'applied' => 'Crédit d’abonnement utilisé',
        'remove' => 'Retirer',
        'line' => 'Crédit d’abonnement',
        'covered' => 'Couvert par l’abonnement',
        'used_line' => 'Crédits utilisés',
        'benefits_section' => 'Avantages d’abonnement',
        'col_service' => 'Prestation',
        'col_included' => 'Inclus',
        'col_used' => 'Utilisé',
        'col_remaining' => 'Restant',
        'covered_by' => 'Couvert par :name · :count restant(s)',
        'used_up' => 'Quota épuisé. Réservable au tarif normal.',
        'each' => ':count crédits chacun',
        'renews' => 'Renouvellement le :date',
        'balance_line' => 'Solde de la prestation',
        'deposit_covered' => 'Aucun acompte nécessaire',
        'deposit_covered_hint' => 'L’abonnement couvre toutes les prestations de cette réservation : il n’y a rien à encaisser d’avance. Le pourboire reste encaissé en totalité.',
        'expires' => 'Expire le :date',
        'membership_id' => 'ID d’abonnement',
        'period' => 'Période d’avantages',
        'plan_details' => 'Détails de la formule',
        'col_reserved' => 'Réservé',
        'col_status' => 'Statut',
        'status_available' => 'Disponible',
        'status_reserved' => 'Réservé',
        'status_used' => 'Utilisé',
        'reserved_for' => 'Réservé pour le :date',
        'reserved_title' => 'Avantage d’abonnement déjà réservé',
        'reserved_body' => 'Cet avantage d’abonnement est réservé pour le rendez-vous du :date. Pour l’utiliser ici, annulez ou modifiez d’abord cette réservation.',
        'reserved_view' => 'Voir la réservation du :date',
        'reserved_cancel' => 'Annuler la réservation du :date',
        'reserved_pay' => 'Payer normalement',
        'reserved_close' => 'Fermer',
    ],

    'cards' => [
        'recurring' => 'Paiement récurrent',
        'recurring_hint' => 'Se renouvelle automatiquement jusqu’à résiliation.',
        'recurring_locked' => 'Cet abonnement est vendu comme un abonnement et se renouvelle toujours.',
        'renews' => 'Se renouvelle :price',
        'one_off' => 'Encaisser chaque renouvellement à l’accueil',
        'title' => 'Carte enregistrée',
        'why' => 'Nécessaire au renouvellement automatique de l’abonnement.',
        'existing' => 'Utiliser une carte existante',
        'add' => '+ Ajouter une carte',
        'default' => 'Par défaut',
        'expires' => 'Expire le :date',
        'expired' => 'Expirée',
        'expiring' => 'Expire ce mois-ci',
        'save' => 'Enregistrer cette carte',
        'save_required' => 'Nécessaire au renouvellement automatique de l’abonnement.',
        'none' => 'Aucune carte enregistrée pour ce client.',
        'unavailable' => 'Impossible d’enregistrer une carte',
        'unavailable_hint' => 'Connectez un prestataire de paiement dans Paramètres → Paiements pour enregistrer des cartes et renouveler automatiquement.',
        'client_first' => 'Choisissez un client avant d’ajouter une carte.',
        'required' => 'Choisissez une carte pour le renouvellement, ou désactivez le paiement récurrent.',
        'adding' => 'Ajout de la carte…',
        'failed' => 'Cette carte n’a pas pu être enregistrée.',
        'cancel' => 'Annuler',
        'summary_recurring' => 'Récurrent',
        'summary_payment_method' => 'Moyen de paiement',
        'summary_next_billing' => 'Prochain prélèvement',
        'yes' => 'Oui',
        'no' => 'Non',
    ],

    'client' => [
        'search' => 'Rechercher par nom, téléphone ou e-mail…',
        'search_label' => 'Rechercher un client par nom, téléphone ou e-mail',
        'or' => 'OU',
        'add' => 'Ajouter un client',
        'guest' => 'Réserver un passage anonyme',
        'none' => 'Aucun client ne correspond.',
        'change' => 'Changer',
        'guest_name' => 'Nom',
        'guest_phone' => 'Mobile',
        'guest_email' => 'E-mail',
        'guest_hint' => 'Un mobile ou un e-mail l’ajoute à la liste des clients, ou rattache cette réservation à sa fiche existante. Le nom seul ne suffit pas.',
        'guest_save' => 'Enregistrer les informations',
        'guest_checking' => 'Vérification…',
        'guest_saved' => 'Enregistré',
        'guest_known' => 'C’est peut-être déjà un client.',
        'guest_known_hint' => 'Utilisez sa fiche et la réservation conserve son historique. Continuez en sans rendez-vous si c’est quelqu’un d’autre.',
    ],

    'service' => [
        'search' => 'Rechercher une prestation…',
        'category' => 'Catégorie de prestation',
        'all_categories' => 'Toutes les catégories',
        'search_categories' => 'Rechercher une catégorie…',
        'chosen' => 'Choisies',
        'none' => 'Aucune prestation ne correspond.',
        'empty' => 'Aucune prestation pour l’instant. Ajoutez-en une et l’écran de réservation la proposera.',
        'minutes' => ':count min',
        'remove' => 'Retirer :name',

        /*
        | Le sélecteur en pleine page.
        |
        | Un salon avec cent prestations ne peut pas en choisir une dans une
        | liste au sein d'un champ, alors choisir devient son propre écran :
        | les catégories d'un côté, les prestations de l'autre, et un seul
        | Enregistrer en bas plutôt qu'une validation par prestation.
        */
        'add' => 'Ajouter / attribuer une prestation',
        'change' => 'Ajouter ou modifier les prestations',
        'card_empty' => 'Aucune prestation choisie.',
        'select_title' => 'Sélectionner les prestations',
        'close' => 'Retour à la réservation',
        'categories' => 'Catégories de prestations',
        'all_services' => 'Toutes les prestations',
        'client_favorites' => 'Favorites du client',
        'selected' => ':count sélectionnées',
        'selected_one' => '1 sélectionnée',
        'selected_none' => 'Rien de sélectionné',
        'save_close' => 'Enregistrer et fermer',
        'nothing_here' => 'Rien dans cette catégorie.',
        'hours' => ':count h',
        'hours_minutes' => ':hours h :minutes min',
        'count' => ':count prestations',
        'count_one' => '1 prestation',
        'needs' => 'Nécessite :names',
    ],

    'when' => [
        'none_in_period' => 'Rien de libre à ce moment de la journée.',
        'change_location' => 'Changer d’établissement',
        'search_locations' => 'Rechercher un établissement…',
        'no_locations' => 'Aucun établissement ne correspond à cette recherche.',
        'closed_date' => 'Aucun créneau — cet établissement est fermé ce jour-là.',
        'too_long' => 'Ces prestations n’entrent pas dans les horaires d’ouverture de cet établissement ce jour-là.',
        'nothing_free' => 'Rien n’est libre ce jour-là — les créneaux sont pris, ou personne n’est de service.',
        'loading_times' => 'Recherche des disponibilités…',
        'staff' => 'Membre de l’équipe',
        'any' => 'N’importe qui de disponible',
        'date' => 'Date',
        'today' => 'Aujourd’hui',
        'tomorrow' => 'Demain',
        'next_3' => '3 prochains jours',
        'next_7' => '7 prochains jours',
        'custom' => 'Date personnalisée',
        'done' => 'Terminé',
        'previous_month' => 'Mois précédent',
        'next_month' => 'Mois suivant',
        'time' => 'Heure de début',
        'ends' => 'Se termine à :time',
        'location' => 'Établissement',
        'morning' => 'Matin',
        'afternoon' => 'Après-midi',
        'evening' => 'Soir',
    ],

    'details' => [
        'source' => 'Origine de la réservation',
        'note' => 'Note de rendez-vous',
        'note_hint' => 'Cette réservation uniquement. Elle ne devient jamais une note client permanente.',
        'note_placeholder' => 'Le client veut la même coupe, mais un peu plus courte aujourd’hui.',
        'client_note' => 'Note client',
        'client_note_aside' => '— conservée sur la fiche client',
        'client_note_hint' => 'Enregistrée sur le client à la confirmation. Toute personne qui le réserve la voit.',
        'client_note_placeholder' => 'Cuir chevelu sensible — pas de chaleur directe sur les racines.',
        'choose_source' => 'Choisissez une origine',
        'search_sources' => 'Rechercher une origine…',
    ],

    'duplicate' => [
        'title' => 'Doublon possible',
        'title_exact' => 'Cela ressemble à la même réservation deux fois',
        'message' => ':client a déjà :service réservé le :date.',
        'message_overlap' => 'Cela chevauche une réservation existante pour le même client et la même prestation.',
        'message_exact' => 'Cela semble être un doublon exact d’une réservation existante — même client, même prestation, même établissement, même horaire.',
        'existing' => 'Déjà réservé',
        'view' => 'Voir la réservation existante',
        'ask' => 'Un client peut vraiment vouloir la même prestation deux fois dans la journée. Continuez si c’est le cas.',
        'continue' => 'Continuer quand même',
        'create_anyway' => 'Créer quand même',
        'cancel' => 'Annuler cette réservation',
        'discard_title' => 'Annuler cette réservation ?',
        'discard_body' => 'Cela supprime la réservation en cours de saisie, ainsi que la demande sous laquelle elle s’enregistrait.',
        'discard_keeps' => 'Le rendez-vous que ce client a déjà n’est pas touché.',
        'keep' => 'Conserver la réservation',
        'discard_confirm' => 'Annuler et supprimer',
        'blocked' => 'Ce client a déjà l’une de ces prestations réservée ce jour-là. Vérifiez l’avertissement sur l’écran de réservation avant de la prendre.',
    ],

    'payment' => [
        'deposit_percent' => 'Acompte',
        'deposit_now' => 'Acompte dû maintenant',
        'deposit_now_percent' => 'Acompte de :percent % dû maintenant',
        'remaining' => 'Solde restant',
        'type' => 'Option de paiement',
        'none' => 'Aucun paiement maintenant',
        'none_hint' => 'Ne rien encaisser pendant la réservation.',
        'deposit' => 'Prendre un acompte',
        'deposit_hint' => 'Encaisser maintenant une partie du total.',
        'full' => 'Paiement intégral',
        'full_hint' => 'Encaisser maintenant la totalité du montant.',
        'amount' => 'Montant de l’acompte',
        /* Les deux façons dont un comptoir énonce un acompte : une politique
           s'écrit en pourcentage, ce qu'on tape est un montant. */
        'preset' => ':percent %',
        'preset_custom' => 'Personnalisé',
        'too_much' => 'Un acompte ne peut pas dépasser le total de la réservation.',
        /* Un acompte que la prestation elle-même impose : on ne demande pas au
           comptoir combien, on le lui dit. */
        'too_little' => 'Ces prestations exigent un acompte d’au moins :amount.',
        'deposit_required' => 'Acompte exigé',
        'deposit_required_error' => 'Ces prestations exigent un acompte : impossible de prendre la réservation sans rien encaisser.',
        'deposit_required_hint' => 'Ces prestations exigent un acompte, il faut donc en encaisser un pour terminer cette réservation. Il peut être augmenté, pas supprimé.',
        'deposit_required_percent' => ':percent % du total de la réservation',
        'balance' => 'Solde dû',
        'collecting' => 'Encaissé maintenant',
        'nothing_collected' => 'Aucun paiement ne sera encaissé pour cette réservation.',
        'action' => 'Mode d’encaissement',
        'choose_action' => 'Choisissez comment c’est encaissé',
        'search_actions' => 'Rechercher…',
        'action_hint' => 'Demandé seulement lorsqu’il y a quelque chose à encaisser.',
        'actions' => [
            'collect-now' => 'Encaisser maintenant',
            'desk' => 'Pris au comptoir',
            'link' => 'Envoyer un lien de paiement',
            'later' => 'Demander à l’arrivée',
            'waive' => 'Dispensé',
        ],
        'action_hints' => [
            'collect-now' => 'Débiter ici, avant de terminer la réservation.',
            'desk' => 'Le comptoir l’encaisse en personne. Le solde reste dû jusque-là.',
            'link' => 'Le client paie quand il veut. Envoyé par e-mail une fois la réservation prise.',
            'later' => 'Encaissé à l’arrivée du client. Le solde reste dû.',
            'waive' => 'Rien n’est encaissé, volontairement. Enregistré à votre nom.',
        ],
        'waiver_reason' => 'Pourquoi c’est dispensé',
        'waiver_placeholder' => 'Une habituée dont la couleur s’est mal passée la dernière fois.',
        'waiver_hint' => 'Conservé avec votre nom et la date, pour que la décision puisse être justifiée plus tard.',
        'waiver_needed' => 'Dites pourquoi le paiement est dispensé.',
        'no_waive_permission' => 'Vous ne pouvez pas dispenser un paiement. Demandez à un responsable.',
        'collect_now_hint' => 'L’écran de paiement s’ouvre dès la réservation prise.',
        'link_sent' => 'Lien de paiement envoyé à :to.',
        'link_status' => 'Lien de paiement',
        'link_statuses' => [
            'sent' => 'Envoyé',
            'opened' => 'Ouvert',
            'paid' => 'Payé',
            'expired' => 'Expiré',
        ],
        'link_no_email' => 'Ce client n’a pas d’adresse e-mail, le lien n’a nulle part où aller.',
    ],

    'comms' => [
        'send' => 'Envoyer la confirmation',
        'both' => 'SMS + e-mail',
        'sms' => 'SMS',
        'email' => 'E-mail',
        'none' => 'Rien',
        'to_sms' => 'SMS à',
        'to_email' => 'E-mail à',
        'missing' => 'Aucun numéro ni adresse au dossier, rien ne peut être envoyé.',
    ],

    'summary' => [
        'client' => 'Client',
        'services' => 'Prestations',
        'staff' => 'Avec',
        'when' => 'Quand',
        'duration' => 'Durée',
        'total' => 'Total',
        'deposit' => 'Acompte',
        'due' => 'Dû le jour même',
        'nothing' => 'Rien de choisi pour l’instant.',
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'reference' => 'Référence de réservation',
        'location' => 'Établissement',
        'resource' => 'Ressource',
        'starts' => 'Début',
        'ends' => 'Fin estimée',
        'subtotal' => 'Sous-total',
        'discount' => 'Remise',
        'coupon' => 'Bon de réduction',
        'tip_due' => 'Pourboire convenu',
        'amount_due' => 'Montant dû',
        'tip_paid' => 'Pourboire déjà versé',
        'due_now' => 'Solde dû',
        'additional_tip' => 'Pourboire supplémentaire',
        'collect_today' => 'Paiement du jour',
        'tax' => 'TVA (:rate)',
        'tax_included' => 'TVA comprise (:rate)',
        'paid' => 'Payé',
        'estimate' => 'Les prix sont confirmés au moment où la réservation est prise.',
        'status' => 'Statut',
        'date' => 'Date',
        'source' => 'Réservé via',
    ],

    /*
    | Le panneau qui s'ouvre à côté de la réservation une fois le client choisi.
    |
    | Deux types de faits cohabitent et sont formulés différemment à dessein :
    | « Demandé nommément » est ce que le client a dit, « Réservé le plus
    | souvent » est ce que l'agenda a remarqué. Un panneau qui les confondrait
    | ferait citer le logiciel aux gens comme s'ils l'avaient demandé.
    */
    'new_client' => [
        'title' => 'Ajouter un client',
        'intro' => 'Juste assez pour prendre la réservation. Le reste se complète plus tard depuis sa fiche.',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'Adresse e-mail',
        'mobile' => 'Numéro de mobile',
        'contact_hint' => 'Un mobile ou un e-mail — l’un des deux, pour que la confirmation ait où aller.',
        'needs_contact' => 'Un mobile ou un e-mail — la confirmation a besoin d’une destination.',
        'duplicate' => 'C’est peut-être déjà un client.',
        'add' => 'Ajouter le client',
        'add_anyway' => 'Ajouter quand même',
        'full_form' => 'Formulaire client complet',
    ],

    'context' => [
        'preferred' => 'Praticien préféré',
        'also_seen' => 'Aussi vu',
        'last' => 'Dernière réservation',
        'again' => 'Reprendre le même rendez-vous',
        'recent' => 'Visites récentes',
        'average' => 'Note moyenne :rating',
        'preferences' => 'Préférences de réservation',
        'none' => 'Aucun historique — c’est sa première réservation.',
        'visits' => '{0} Aucune visite précédente|{1} :count visite précédente|[2,*] :count visites précédentes',
        'from_diary' => 'D’après l’historique',
        'kinds' => [
            'asked_for' => 'Demandé nommément',
            'most_booked' => 'Réservé le plus souvent',
            'also_seen' => 'Déjà vu',
        ],
        'cadence' => '{1} Réserve à peu près chaque semaine|[2,*] Réserve à peu près toutes les :count semaines',
        'windows' => [
            'morning' => 'Préfère les rendez-vous le matin',
            'afternoon' => 'Préfère les rendez-vous l’après-midi',
            'evening' => 'Préfère les rendez-vous le soir',
        ],
        'walk_in' => 'Passage sans rendez-vous',
        'walk_in_hint' => 'Aucune trace n’est conservée au-delà de ce rendez-vous.',
        'remove' => 'Retirer ce client de la réservation',
    ],

    'confirm' => 'Confirmer la réservation',
    'draft' => 'Enregistrer comme brouillon',
    'cancel' => 'Annuler',

    /*
    | Ce qui manque encore, une chose à la fois. La liste de toutes les
    | questions sans réponse est un mur ; la suivante est une consigne.
    */
    'lead' => [
        'created' => 'Référence :reference créée. Aucune action n’est nécessaire pour l’instant.',
    ],

    /*
    | La réservation s'enregistre au fil de la saisie.
    |
    | Dit discrètement et à côté de la référence, parce que ce n'est pas une
    | nouvelle : la personne à l'accueil parle à quelqu'un, et un enregistrement
    | qui s'annoncerait toutes les quelques secondes serait la chose la plus
    | bruyante à l'écran.
    */
    'autosave' => [
        'reference' => 'Réf. réservation : :reference',
        'saving' => 'Enregistrement…',
        'saved' => 'Enregistré',
        'failed' => 'Non enregistré',
        'draft' => 'Brouillon',
    ],

    'steps' => [
        'save' => 'Enregistrer et continuer',
        'edit' => 'Modifier',
    ],

    'blockers' => [
        'client' => 'Choisissez un client, ou prenez-le en sans rendez-vous.',
        'guest' => 'Donnez un nom au passage sans rendez-vous.',
        'service' => 'Choisissez au moins une prestation.',
        'time' => 'Choisissez une heure de début.',
        'ready' => 'Prêt à réserver.',
    ],

    'booked' => 'Réservation confirmée pour :name.',
    'no_time_yet' => 'Pas encore d’horaire',
    'saved_draft' => 'Réservation enregistrée comme brouillon.',

    'empty' => 'Aucune réservation ne correspond à ces filtres.',
    'empty_hint' => 'Effacez les filtres pour voir tous les rendez-vous.',
    'none_yet' => 'Aucune réservation pour l’instant',
    'none_yet_hint' => 'Prenez la première et elle apparaîtra ici.',

    'showing' => 'Affichage de :from à :to sur :total réservations',
    'results' => [
        'zero' => 'Aucune réservation',
        'one' => '1 réservation',
        'many' => ':count réservations',
        'clear' => 'Effacer les filtres',
    ],
    'actions_for' => 'Actions pour :name',

    /*
    | L'argent rattaché à une réservation.
    |
    | Presque tous les paiements qu'un salon encaisse se font ailleurs — dans
    | le tiroir, sur le terminal près de la caisse, dans l'appli bancaire de
    | quelqu'un — donc le vocabulaire parle de noter ce qui s'est passé, pas de
    | débiter qui que ce soit. « Marquer comme payé » est le mot d'une personne,
    | et il le dit.
    */
    /*
    | La page de détail de la réservation.
    |
    | Se lit comme la fiche client : la personne à gauche, le travail au milieu,
    | ce qu'il faut en faire à droite.
    */
    /*
    | Ce qu'on peut faire d'une réservation une fois prise.
    |
    | Quatre actes, et chacun pose les deux mêmes questions : pourquoi, et y
    | a-t-il autre chose. Les motifs eux-mêmes appartiennent à l'établissement —
    | définis dans Réglages → Motifs — donc rien ici n'en nomme un.
    */
    /*
    | Les vues dans lesquelles un comptoir travaille.
    |
    | Pas des recherches enregistrées : chacune est une question que l'accueil
    | pose vraiment entre neuf et dix-huit heures, ce qui explique aussi que la
    | page s'ouvre sur Aujourd'hui.
    */
    'tabs' => [
        'today' => 'Aujourd’hui',
        'next-3' => '3 prochains jours',
        'month' => 'Mois',
        'check-in' => 'Enregistrement en attente',
        'more' => 'Plus',
        'all' => 'Toutes les réservations',
        'completed' => 'Terminées',
        'cancelled' => 'Annulées',
        'no-shows' => 'Absences',
        'declined' => 'Refusées',

        'summary' => [
            'total' => 'Réservations du jour',
            'checked_in' => 'Arrivés',
            'pending' => 'Enregistrement en attente',
            'completed' => 'Terminées',
            'no_show' => 'Absence',
        ],

        /* Où ils en sont et — tant qu'ils sont attendus — de combien ils
           décalent. « 12 min de retard » est ce sur quoi l'accueil agit ;
           l'heure prévue seule oblige quelqu'un à lire une pendule et à
           calculer, quarante fois par matinée. */
        'arrival' => [
            'checked_in' => 'Arrivé',
            'waiting' => 'Pas encore arrivé',
            'not_due' => 'Pas attendu aujourd’hui',
            'done' => 'Vu',
            'absent' => 'N’est pas venu',
            'due' => 'Attendu maintenant',
            'later' => 'Plus tard aujourd’hui',
            'early' => ':count min d’avance',
            'late' => ':count min de retard',
        ],

        'previous_month' => 'Mois précédent',
        'next_month' => 'Mois suivant',
        'empty' => [
            'today' => 'Rien de réservé pour aujourd’hui.',
            'next-3' => 'Rien de réservé sur les trois prochains jours.',
            'check-in' => 'Personne n’attend d’être enregistré.',
        ],
    ],

    'resources' => [
        'none_available' => 'Aucune ressource disponible pour cette prestation à l’horaire choisi.',
        'auto' => 'Attribuée automatiquement',
        'manual' => 'Choisie manuellement',
        'change' => 'Changer de ressource',
        'choose' => 'Sélectionner une ressource',
        'unavailable' => 'Indisponible',
        'currently' => 'Actuellement attribuée',
        'updated' => 'Ressource mise à jour : :name',
    ],

    'status' => [
        'check-in' => [
            'action' => 'Enregistrer l’arrivée',
            'title' => 'Enregistrer l’arrivée du client',
            'intro' => 'Le client est là. Son rendez-vous passe en « arrivé » et l’heure est enregistrée.',
            'confirm' => 'Enregistrer l’arrivée',
            'note' => 'Note d’arrivée',
            'note_hint' => 'Facultatif. « Arrivé dix minutes en avance », et tout ce que l’équipe devrait savoir.',
            'done_at' => 'Arrivée enregistrée à :time par :name',
            'already' => 'Arrivé',
        ],
        /* Aucun motif et pratiquement aucune boîte de dialogue : terminer un
           rendez-vous est l'issue ordinaire, et une liste obligatoire devant
           serait remplie de la même façon à chaque fois. */
        'complete' => [
            'action' => 'Terminer',
            'title' => 'Terminer le rendez-vous',
            'intro' => 'Le travail est fait. Le rendez-vous est enregistré comme réalisé, et le client est invité à donner son avis si les demandes d’avis sont activées.',
            'confirm' => 'Terminer le rendez-vous',
            'note' => 'Note de clôture',
            'note_hint' => 'Facultatif. Pour l’équipe, pas pour le client.',
        ],
        'no-show' => [
            'action' => 'Absence',
            'title' => 'Marquer la réservation comme absence',
            'intro' => 'Le rendez-vous reste au dossier ; le client est marqué comme n’étant pas venu.',
            'reason' => 'Motif',
            'confirm' => 'Enregistrer',
        ],
        'cancelled' => [
            'action' => 'Annuler la réservation',
            'title' => 'Annuler la réservation',
            'intro' => 'Le rendez-vous est décommandé et le créneau qu’il occupait est rendu.',
            'reason' => 'Motif d’annulation',
            /* « Conserver la réservation » plutôt que « Annuler » : dans une
               boîte de dialogue sur l'annulation, un bouton qui dit Annuler est
               celui que personne ne lit deux fois de la même façon. */
            'dismiss' => 'Conserver la réservation',
            'confirm' => 'Annuler la réservation',
        ],
        'declined' => [
            'action' => 'Refuser la réservation',
            'title' => 'Refuser la réservation',
            'intro' => 'La demande est refusée. Rien n’est réservé et le client peut en connaître la raison.',
            'reason' => 'Motif du refus',
            'confirm' => 'Refuser la réservation',
        ],
        'reschedule' => [
            'action' => 'Reporter la réservation',
            'title' => 'Reporter la réservation',
            'intro' => 'La même réservation, à un autre horaire. La référence, le client et la note restent tels quels.',
            'reason' => 'Motif du report',
            'confirm' => 'Enregistrer le report',
            'current' => 'Actuellement',
            'new_date' => 'Nouvelle date',
            'new_time' => 'Nouvelle heure',
            'staff' => 'Membre de l’équipe',
            'location' => 'Établissement',
            'pick_date' => 'Choisissez une date pour voir les disponibilités.',
            'no_slots' => 'Rien n’est libre ce jour-là.',
            'loading' => 'Recherche des disponibilités…',
        ],

        'choose_reason' => 'Choisissez un motif',
        'note' => 'Note',
        'note_hint' => 'Facultatif. Pour l’équipe, pas pour le client.',
        'details' => 'Précisions',
        'details_hint' => 'Obligatoire pour ce motif.',
        'details_required' => 'Ce motif demande une explication.',
        'reason_unavailable' => 'Ce motif n’est plus disponible. Choisissez-en un autre.',
        'slot_taken' => 'Ce créneau n’est pas libre. Choisissez-en un autre.',
        'dismiss' => 'Annuler',

        'done' => [
            'check-in' => 'Arrivée du client enregistrée.',
            'complete' => 'Rendez-vous terminé.',
            'no-show' => 'Marqué comme absence.',
            'cancelled' => 'Réservation annulée.',
            'declined' => 'Réservation refusée.',
            'reschedule' => 'Réservation reportée.',
        ],
    ],

    /*
    | L'historique propre à la réservation : tout ce qui lui a été fait, avec
    | les mots que les motifs portaient ce jour-là plutôt que ceux d'aujourd'hui.
    */
    'activity' => [
        'title' => 'Activité de la réservation',
        'none' => 'Rien n’est encore arrivé à cette réservation.',
        'system' => 'StyleDesk',
        'by' => 'par :name',
        'reason' => 'Motif',
        'note' => 'Note',
        'previous' => 'Avant',
        'new' => 'Après',
        'events' => [
            'no-show' => 'Réservation marquée comme absence',
            'cancelled' => 'Réservation annulée',
            'declined' => 'Réservation refusée',
            'confirmed' => 'Réservation reportée',
            'pending' => 'Réservation reportée',
            'arrived' => 'Arrivée du client enregistrée',
        ],
    ],

    'detail' => [
        'payment_status' => 'Paiement',
        'book_again' => 'Réserver à nouveau',
        'cancel_booking' => 'Annuler la réservation',
        'reschedule' => 'Reporter',
        'soon_hint' => 'Pas encore développé — annuler et déplacer un rendez-vous modifient tous deux l’agenda.',
        'services' => 'Détail des prestations',
        'notes' => 'Notes de réservation',
        'no_notes' => 'Rien n’a été noté sur ce rendez-vous.',
        'payment_summary' => 'Récapitulatif du paiement',
        'take_payment' => 'Encaisser',
        'paid_in_full' => 'Payé intégralement',
        'transactions' => 'Transactions',
        'no_transactions' => 'Aucun montant n’a encore été encaissé sur cette réservation.',
        'recorded_by' => 'enregistré par :name',
        'taken_by' => 'Encaissé par :name le :when',
        'someone' => 'un membre de l’équipe',
        'updated' => 'dernière mise à jour :when',
        'walk_in' => 'Reçu sur place.',
        /* Dit explicitement, parce que la fiche et cette page divergeront à
           mesure que le client change — et c'est bien l'intérêt. */
        'snapshot' => 'Tel qu’au :when, lorsque cette réservation a été prise.',
    ],

    'pay' => [
        'coupon' => 'Code de réduction',
        'coupon_placeholder' => 'Saisissez le code',
        'apply' => 'Appliquer',
        'remove_coupon' => 'Retirer',
        'add_tip' => 'Ajouter un pourboire',
        'no_tip' => 'Pas de pourboire',
        'custom_tip' => 'Personnalisé',
        'discount' => 'Remise',
        'tip' => 'Pourboire',
        'total_due' => 'Total dû',
        'paying_by' => 'Paiement par',
        'paying_by_hint' => 'Certaines prestations coûtent un montant différent en espèces. Le total suit ce choix.',
        'title' => 'Paiement',
        'due' => 'Montant dû',
        'collect_now' => 'Montant à encaisser maintenant',
        'booking_total' => 'Total de la réservation',
        'remaining' => 'Solde restant',
        'method' => 'Comment paie-t-il ?',
        'change_method' => 'Changer',
        'back' => 'Retour au récapitulatif',
        'again' => 'Enregistrer et continuer',
        'skip' => 'Confirmer sans paiement',
        'skip_hint' => 'La réservation est prise et le solde reste dû.',
        'pay_amount' => 'Payer :amount',
        'record' => 'Enregistrer un paiement en espèces',
        'mark_paid' => 'Marquer comme payé',
        'marking' => 'Enregistrement…',
        'amount' => 'Montant',
        'received' => 'Montant reçu',
        'change' => 'Monnaie à rendre',
        'reference' => 'Référence',
        'reference_hint' => 'Ce que le paiement affiche de leur côté — facultatif.',
        'cardholder' => 'Nom du titulaire',
        'card_number' => 'Numéro de carte',
        'expiry' => 'Date d’expiration',
        'cvv' => 'CVV',
        'zip' => 'Code postal de facturation',
        'card_safe' => 'Les données de carte vont directement au prestataire de paiement. StyleDesk ne les conserve jamais.',
        'nothing_to_take' => 'Saisissez un montant ou un pourboire : un paiement de rien n’est pas un paiement.',
        'no_card_provider' => 'Aucun prestataire de carte n’est connecté, StyleDesk ne peut donc pas encaisser la carte lui-même. Prenez-la sur le terminal et enregistrez-la ci-dessous.',
        'terminal' => 'Pris sur le terminal',
        'not_ready' => 'Pas encore configuré. Ajoutez le compte dans les réglages de l’établissement.',
        'cash_only' => 'Cette réservation est tarifée en espèces, elle se règle donc en espèces.',
        'cash_only_row' => 'Indisponible — cette réservation est tarifée en espèces.',
        'handle_hint' => 'Lisez ceci à voix haute, puis marquez le paiement comme reçu.',
        'short_cash' => 'C’est moins que le montant à payer.',
        'failed' => 'Ce paiement n’a pas pu être enregistré. Rien n’a été débité — réessayez.',
        'partial' => ':paid sur :total payés · :due encore dus',
    ],

    'methods' => [
        'card' => ['name' => 'Carte bancaire', 'hint' => 'Débitée maintenant, ou prise sur le terminal'],
        'cash' => ['name' => 'Espèces', 'hint' => 'Comptées au comptoir'],
        'paypal' => ['name' => 'PayPal', 'hint' => 'Envoyé sur le PayPal de l’établissement'],
        'zelle' => ['name' => 'Zelle', 'hint' => 'Envoyé sur le Zelle de l’établissement'],
        'cash-app' => ['name' => 'Cash App', 'hint' => 'Envoyé sur le Cash App de l’établissement'],
        'venmo' => ['name' => 'Venmo', 'hint' => 'Envoyé sur le Venmo de l’établissement'],
    ],

    'payment_statuses' => [
        'authorized' => ['label' => 'Autorisé'],
        'cancelled' => ['label' => 'Annulé'],
        'disputed' => ['label' => 'Contesté'],
        'chargeback' => ['label' => 'Rétrofacturation'],
        'unpaid' => ['label' => 'Impayée'],
        'partial' => ['label' => 'Partiellement payée'],
        'paid' => ['label' => 'Payée'],
        'pending' => ['label' => 'Paiement en attente'],
        'failed' => ['label' => 'Échec'],
        'refunded' => ['label' => 'Remboursée'],
        'partially-refunded' => ['label' => 'Partiellement remboursée'],
    ],

    /* The confirmation as a text message. One segment where it can be:
       a text is charged by the 160 characters, and a template that
       quietly became three is a bill nobody agreed to. */
    'sms' => [
        'confirmation' => ':business via StyleDesk : Bonjour :name, votre rendez-vous est confirmé le :date à :time avec :staff. Répondez YES pour confirmer, CANCEL pour demander un changement, STOP pour vous désinscrire. Réf :reference',
    ],

    'confirmation' => [
        'title' => 'Réservation confirmée',
        'made' => 'Le rendez-vous est dans l’agenda.',
        'payment' => 'Paiement',
        'view' => 'Voir la réservation',
        'another' => 'Créer une autre réservation',
        'to_list' => 'Retour aux réservations',
        'print' => 'Imprimer la confirmation',
        'receipt' => 'Télécharger le reçu',
        'receipt_title' => 'Reçu',
        'send' => 'Envoyer la confirmation',
        'sending' => 'Envoi…',
        'sent' => 'Confirmation envoyée à :to.',
        'no_email' => 'Ce client n’a pas d’adresse e-mail au dossier.',
        'no_mobile' => 'Cette réservation n’a pas de numéro de mobile. Ajoutez-en un au client, ou envoyez par e-mail.',
        'no_sms_consent' => 'Ce client a désactivé les SMS. Envoyez-le par e-mail à la place.',
        'link_failed' => 'La réservation est prise, mais le lien de paiement n’a pas pu être envoyé par e-mail. Appelez plutôt le client.',
        'due_notice' => 'Paiement dû :amount',
        'amount_due' => 'Montant dû',
    ],

    'email' => [
        'subject' => ':business — votre rendez-vous du :date',
        'headline' => 'Votre rendez-vous est confirmé',
        'intro' => 'Merci, :name. Voici les détails.',
        'footer_note' => 'Besoin de modifier ou d’annuler ? Répondez à cet e-mail ou appelez-nous.',
        'link_subject' => ':business — régler votre rendez-vous du :date',
        'link_headline' => 'Comment régler votre rendez-vous',
        'link_intro' => 'Merci, :name. Voici ce qui reste dû, et comment le régler.',
        'link_amount' => 'Montant à payer',
        'link_cta' => 'Voir les détails du paiement',
        'link_expiry' => 'Ce lien fonctionne jusqu’au :when.',
    ],

    /*
    | La page sur laquelle un client atterrit depuis un lien de paiement.
    |
    | Écrite pour quelqu'un qui n'est pas utilisateur de StyleDesk et ne le sera
    | jamais : elle dit ce qui est dû, où l'envoyer, et rien du fonctionnement
    | interne de l'établissement.
    */
    'pay_link' => [
        'title' => 'Régler votre rendez-vous',
        'amount' => 'Montant demandé',
        'balance' => 'Solde de cette réservation : :amount',
        'how' => 'Comment payer',
        'reference_hint' => 'Merci d’indiquer :reference pour que nous puissions rapprocher le paiement de votre rendez-vous.',
        'no_handles' => 'Appelez-nous et nous le prendrons par téléphone.',
        'expired' => 'Ce lien de paiement a expiré.',
        'expired_hint' => 'Contactez-nous et nous vous en enverrons un nouveau.',
        'settled' => 'Cette réservation est payée intégralement.',
        'settled_hint' => 'Plus rien n’est dû. Merci.',
        'footer' => 'Ce lien concerne un seul rendez-vous et ne vous connecte à rien.',
    ],

    'validation' => [
        'who' => 'Choisissez un client, ou donnez un nom au passage sans rendez-vous.',
    ],
];
