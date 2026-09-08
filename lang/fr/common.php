<?php

declare(strict_types=1);

/*
| Les mots que toute l'application emploie. Tout ce qui apparaît sur plus d'un
| écran vit ici plutôt que dans le fichier de cet écran, pour que « Enregistrer »
| soit traduit une seule fois et ne puisse pas ressortir de deux façons.
*/

return [
    'retry' => 'Réessayer',
    'save' => 'Enregistrer',
    'save_changes' => 'Enregistrer les modifications',
    'done' => 'Terminé',
    'cancel' => 'Annuler',
    'edit' => 'Modifier',
    /** The accessible name for a card's Edit control; :section is the card title. */
    'edit_section' => 'Modifier :section',
    'delete' => 'Supprimer',
    'remove' => 'Retirer',
    'add' => 'Ajouter',
    'custom_color' => 'Couleur personnalisée',
    'back' => 'Retour',
    'close' => 'Fermer',
    'dismiss' => 'Fermer',
    'search' => 'Rechercher',
    'filter' => 'Filtrer',
    'clear' => 'Effacer',
    'clear_all' => 'Tout effacer',
    'apply' => 'Appliquer',
    'show_more' => 'Afficher plus',
    'show_less' => 'Afficher moins',
    'view' => 'Voir',
    'yes' => 'Oui',
    'no' => 'Non',
    'optional' => '(facultatif)',
    'not_set' => 'Non renseigné',
    'on' => 'Activé',
    'off' => 'Désactivé',
    'none' => 'Aucun',
    'active' => 'Actif',
    'inactive' => 'Inactif',
    'saving' => 'Enregistrement…',
    'loading' => 'Chargement…',

    /**
     * Le sélecteur de date partagé (x-date-field).
     *
     * Les noms de mois et de jours ne sont pas listés ici — Carbon les connaît
     * déjà dans chaque langue proposée, et une liste de douze entretenue à la
     * main ne serait qu'un second endroit où elles pourraient diverger.
     */
    'choose_a_date' => 'Choisir une date',
    'today' => 'Aujourd’hui',
    'month' => 'Mois',
    'year' => 'Année',
    'previous_month' => 'Mois précédent',
    'next_month' => 'Mois suivant',
    'language' => 'Langue',
    'coming_soon' => 'Bientôt',
    'setup_required' => 'Configuration requise',
    'view_only' => 'Lecture seule',

    /*
     * L'outil d'envoi d'images partagé. Utilisé aujourd'hui par la photo de
     * profil du personnel et par tout futur champ image, ses mots vivent donc
     * ici plutôt que dans un module.
     */
    'upload' => [
        'choose' => 'Choisir une image',
        'progress' => 'Progression de l’envoi',
        'cancel' => 'Annuler l’envoi',
        'too_large' => 'Cette image dépasse 2 Mo.',
        'failed' => 'Cette image n’a pas pu être envoyée. Réessayez.',
    ],
    /** Si une personne a accepté d'être contactée. Rendu par <x-consent-status>. */
    'consent' => [
        'opted_in' => 'A consenti',
        'opted_out' => 'A refusé',
    ],
    /** Demandé avant de retirer quoi que ce soit d'une fiche. */
    'confirm' => [
        'deactivate' => 'Désactiver',
        'activate' => 'Activer',
        'remove_tag_title' => 'Retirer l’étiquette client ?',
        'remove_tag' => 'Retirer « :label » de ce client ?',
        'remove_behavioral_title' => 'Retirer l’étiquette comportementale ?',
        'remove_behavioral' => 'Retirer « :label » de ce client ?',
    ],

    /*
    | Les messages de validation que le navigateur écrit pendant qu'un
    | formulaire se remplit. Formulés comme ce que dit le serveur lorsqu'il
    | refuse la même valeur, pour que corriger un champ avant l'envoi et le
    | corriger après se lisent à l'identique. :field est le libellé du champ.
    */
    'validation' => [
        'required' => ':field est obligatoire.',
        'email' => 'Saisissez une adresse e-mail valide.',
        'url' => 'Saisissez une adresse de site valide, commençant par https://',
        'phone' => 'Saisissez un numéro de téléphone valide.',
        'date' => 'Saisissez une date valide.',
        'numeric' => ':field doit être un nombre.',
        'integer' => ':field doit être un nombre entier.',
        'min' => ':field doit contenir au moins :min caractères.',
        'max' => ':field doit contenir :max caractères au maximum.',
        'min_value' => ':field doit être supérieur ou égal à :min.',
        'max_value' => ':field doit être inférieur ou égal à :max.',
        'taken' => 'Cette valeur est déjà utilisée.',
    ],
    'type_a_time' => 'Saisissez une heure, par ex. 14:30',

    /*
    | L'habillage que dessine chaque gabarit : le bandeau d'annonce au-dessus
    | de la barre d'application, le pied de page en dessous, et la bannière qui
    | apparaît quand les ressources d'une page sont périmées.
    |
    | Ici plutôt que dans un gabarit parce que quatre gabarits dessinent le même
    | pied de page, et qu'une chaîne saisie dans chacun d'eux est une chaîne
    | traduite dans trois d'entre eux seulement.
    */
    'stale_assets' => 'Cette page n’est plus à jour, certaines de ses parties ne fonctionneront pas. ',
    'reload' => 'Recharger',
    'legal' => 'Mentions légales',
    'terms' => 'Conditions',
    'privacy' => 'Confidentialité',
    'support' => 'Assistance',
    'all_rights_reserved' => '© :year StyleDesk. Tous droits réservés.',

    'banner' => [
        'watch_now' => 'À voir : bien démarrer avec StyleDesk',
        /* trans_choice, et non Str::plural() : l'assistant ne connaît que
           l'anglais et aurait écrit « 2 day » dans toutes les autres langues.
           Chaque langue énonce ici son propre pluriel, et la phrase entière est
           une seule chaîne pour que l'ordre des mots puisse aussi différer. */
        'trial_remaining' => '{0} Votre essai gratuit se termine aujourd’hui|{1} Il reste :count jour d’essai gratuit|[2,*] Il reste :count jours d’essai gratuit',
        'trial_ending' => 'Votre essai se termine bientôt',
        'subscribe' => 'S’abonner',
    ],

    'session' => [
        'expiring' => 'Votre session va bientôt expirer',
        'expiring_body' => 'Pour votre sécurité, vous allez être déconnecté faute d’activité.',
        /* :time est le compte à rebours, entouré de son propre élément par la vue. */
        'countdown' => 'La session expire dans :time',
        'sign_out' => 'Se déconnecter',
        'stay' => 'Rester connecté',
        'expired' => 'Votre session a expiré',
        'expired_body' => 'Votre session s’est terminée faute d’activité. Reconnectez-vous pour continuer.',
        'sign_in' => 'Se connecter',
    ],
];
