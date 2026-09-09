<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pourboires
|--------------------------------------------------------------------------
|
| Deux questions, et elles sont différentes : est-ce que cette entreprise
| accepte les pourboires, et lesquelles de ses prestations en reçoivent. Un
| salon qui laisse un pourboire à ses coiffeurs n'en laisse pas à l'étagère
| d'où sort un flacon de shampoing.
|
*/

return [

    'title' => 'Pourboires',
    'intro' => 'Si l’on propose aux clients de laisser un pourboire, ce qu’on leur propose, et à quelles prestations cela s’applique.',
    'back' => 'Réglages',

    'enable' => 'Accepter les pourboires',
    'enable_hint' => 'Ajoute une étape pourboire en caisse. La désactiver masque la question et conserve tout ce qui suit exactement tel que vous l’avez laissé.',
    'disabled_note' => 'Les pourboires sont désactivés. Ce qui est réglé ici est conservé et revient dès que vous les réactivez.',

    /* Deux onglets une fois les pourboires activés : quoi proposer, et à
       quelles prestations cela s'applique. */
    'tabs' => [
        'suggest' => 'Quoi proposer',
        'services' => 'Prestations',
    ],

    'defaults' => 'Quoi proposer',
    'defaults_hint' => 'Ce qu’une prestation utilise quand elle n’a rien dit d’autre.',
    'tip_type' => 'Type de pourboire',
    'types' => [
        'percent' => 'Pourcentage',
        'fixed' => 'Montant fixe',
    ],
    'default_tip' => 'Pourboire par défaut',
    'default_tip_hint' => 'Un pourcentage de la part pourboirable de la note, ou une somme forfaitaire.',
    'tip_amount' => 'Montant du pourboire',
    'tip_amount_hint' => 'Une somme fixe, proposée en caisse à la place du pourcentage de l’établissement.',
    'follows_default_note' => 'Propose la valeur par défaut de l’établissement, :amount, en caisse. Elle reste modifiable sur la réservation.',

    'percentages' => 'Proposé en caisse',
    'percentages_hint' => 'Jusqu’à six. Le client dispose aussi d’un champ pour saisir le sien.',

    'require_selection' => 'Demander au client de choisir',
    'require_selection_hint' => 'Il doit répondre avant que le paiement passe. Répondre « Pas de pourboire » compte comme une réponse — c’est une invite, pas un débit.',
    'allow_no_tip' => 'Proposer « Pas de pourboire »',
    'allow_no_tip_hint' => 'Recommandé. Sans cela, le client n’a aucun moyen de refuser.',

    /* ------------------------------------------------------------ prestations */

    'services' => 'Prestations',
    'services_hint' => 'Quelles prestations reçoivent un pourboire, et ce que chacune propose. Vide suit le réglage ci-dessus.',
    'columns' => [
        'service' => 'Prestation',
        'category' => 'Catégorie',
        'price' => 'Prix',
        'tips' => 'Pourboires',
        'default' => 'Pourboire par défaut',
        'type' => 'Type',
        'required' => 'Choix obligatoire',
        'no_tip' => 'Autorise « Pas de pourboire »',
        'status' => 'Statut',
    ],
    'follows_default' => 'Suit la valeur par défaut',
    'follows_default_with' => 'Suit la valeur par défaut (:amount)',
    'offered_at_till' => 'Proposé en caisse',
    'accepted' => 'Acceptés',
    'not_accepted' => 'Sans pourboire',
    'no_services' => 'Aucune prestation active à configurer pour l’instant.',

    'edit_service' => 'Réglages de pourboire',
    'edit_service_for' => 'Pourboires — :name',
    'service_card_hint' => 'Comment le pourboire fonctionne pour cette prestation. Il part de vos valeurs par défaut dans Réglages → Pourboires et peut être changé ici sans y toucher.',
    'accepts' => 'Accepter les pourboires pour cette prestation',
    'accepts_hint' => 'À désactiver pour tout ce sur quoi personne n’a travaillé — la vente au détail, par exemple.',

    /* ------------------------------------------------------------- la caisse */

    'panel' => [
        'title' => 'Pourboire',
        'eligible' => 'Pourboire calculé sur',
        'custom' => 'Personnalisé',
        'none' => 'Pas de pourboire',
        'selected' => 'Pourboire',
        'required' => 'Choisissez une option de pourboire avant d’encaisser.',
        'not_eligible' => 'Rien dans cette réservation ne reçoit de pourboire.',
    ],

    'saved' => 'Réglages de pourboire enregistrés.',
    'enabled' => 'Les pourboires sont activés.',
    'disabled' => 'Les pourboires sont désactivés. Vos réglages sont conservés.',
    'service_saved' => 'Réglages de pourboire de la prestation enregistrés.',
];
