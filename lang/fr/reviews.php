<?php

declare(strict_types=1);

/*
| Avis et retours clients.
|
| Deux publics dans un même fichier, séparés par leurs clés de premier niveau :
| tout ce qui est sous `page` et `email` est lu par un client qui n'a jamais
| entendu parler de StyleDesk, et tout le reste par l'entreprise. La formulation
| côté client est volontairement courte — le §33 est tout le cahier des charges,
| et chaque phrase de plus entre le lien et l'étoile coûte un avis.
*/

return [

    'title' => 'Avis et retours',

    'stars' => '{1} 1 étoile|[2,*] :count étoiles',

    /* Ce que les étoiles veulent dire, pour l'étiquette à côté de celle qu'on
       est en train de choisir. */
    'rating_labels' => [
        1 => 'Très mauvais',
        2 => 'Mauvais',
        3 => 'Moyen',
        4 => 'Bien',
        5 => 'Excellent',
    ],

    /* Où en est l'entreprise avec un retour. */
    'statuses' => [
        'new' => 'Nouveau',
        'reviewing' => 'En cours d’examen',
        'contacted' => 'Client contacté',
        'resolved' => 'Résolu',
        'closed' => 'Clos',
        'no_action' => 'Aucune action requise',
    ],

    /* Où en est la demande, sur le panneau de la réservation. */
    'request_statuses' => [
        'not_requested' => 'Pas demandé',
        'scheduled' => 'Planifié',
        'sent' => 'Envoyé',
        'completed' => 'Terminé',
    ],

    /* ---------------------------------------------------------- la page -- */

    'page' => [
        'title' => 'Comment s’est passée votre visite ?',
        'question' => 'Comment s’est passée votre expérience ?',
        'question_hint' => 'Touchez une étoile. C’est tout ce dont nous avons besoin.',

        'comment_label' => 'Parlez-nous de votre expérience',
        'comment_optional' => 'Facultatif',
        'comment_placeholder' => 'Dites-nous ce que vous avez aimé ou ce que nous pourrions améliorer.',

        'recommend_label' => 'Nous recommanderiez-vous à un ami ?',
        'recommend' => [
            'yes' => 'Oui',
            'maybe' => 'Peut-être',
            'no' => 'Non',
        ],

        /* Affiché dès qu'une note basse est choisie. Des mots différents, parce
           que la question est une autre question — §14. */
        'sorry_title' => 'Nous sommes désolés que votre expérience n’ait pas été à la hauteur.',
        'sorry_hint' => 'Dites-nous comment nous pourrions nous améliorer.',
        'contact_label' => 'Je souhaite qu’une personne de l’établissement me contacte.',

        'submit' => 'Envoyer',
        'submit_negative' => 'Envoyer mon retour',
        'choose_rating' => 'Choisissez d’abord une note.',

        'thanks_title' => 'Merci pour votre retour !',
        'thanks_positive' => 'Nous sommes ravis que votre visite vous ait plu. Souhaitez-vous partager votre expérience ?',
        'thanks_negative' => 'Merci de nous l’avoir dit. Nous nous en servirons pour corriger le tir.',
        'thanks_contact' => 'Quelqu’un de l’établissement vous contactera.',
        'google_cta' => 'Laissez-nous un avis sur Google',
        'done' => 'Terminé',

        'already' => 'Merci. Votre retour a déjà été envoyé.',
    ],

    /* --------------------------------------------------------- l’e-mail -- */

    'email' => [
        'subject' => 'Comment s’est passée votre visite chez :business ?',
        'preview' => 'Cela prend une seconde.',
        'headline' => 'Comment s’est passée votre visite ?',
        'intro' => 'Bonjour :name — nous aimerions beaucoup connaître votre expérience chez :business.',
        'rate' => 'Notez votre expérience',
        'cta' => 'Laisser un retour',
    ],

    /* ------------------------------------------------------ les réglages -- */

    'settings' => [
        'title' => 'Avis et retours',
        'intro' => 'Demandez aux clients comment s’est passée leur visite après un rendez-vous terminé, rattrapez les mécontents avant qu’ils ne s’expriment publiquement, et orientez les autres vers votre fiche.',

        'enable' => 'Activer les avis clients',
        'enable_hint' => 'Quand c’est désactivé, rien n’est envoyé. Les avis déjà donnés restent sur la fiche client et dans vos rapports.',
        'disabled_note' => 'Activez-le pour choisir quand les clients sont sollicités, comment, et où les clients satisfaits sont ensuite dirigés.',

        'timing' => 'Quand demander',
        'timing_hint' => 'Compté à partir du moment où le rendez-vous est terminé. Une heure convient généralement : assez pour que le client soit parti, assez tôt pour qu’il s’en souvienne.',
        'delays' => [
            'immediate' => 'Immédiatement',
            '1h' => '1 heure après la fin',
            '3h' => '3 heures après la fin',
            '6h' => '6 heures après la fin',
            'next_day' => 'Le lendemain',
        ],

        'channel' => 'Comment demander',
        'channel_hint' => 'Un client sans adresse au dossier n’est pas sollicité. Les SMS ne sont pas encore disponibles.',
        'channels' => [
            'email' => 'E-mail',
            'sms' => 'SMS',
            'both' => 'SMS + e-mail',
        ],
        'coming_soon' => 'Bientôt',

        'google' => 'Avis Google',
        'google_enable' => 'Proposer un avis Google aux clients satisfaits',
        'google_hint' => 'Montré uniquement aux clients qui mettent 4 ou 5 étoiles. Ceux qui en mettent moins sont invités à dire comment vous pourriez vous améliorer, et ne sont jamais envoyés vers une fiche publique.',
        'google_urls' => 'Liens d’avis par établissement',
        'google_urls_hint' => 'Chaque succursale a sa propre fiche Google. Une succursale sans lien ne propose simplement pas le bouton.',
        'google_url_placeholder' => 'https://g.page/r/…',
        'no_locations' => 'Ajoutez un établissement avant de définir des liens d’avis Google.',

        'saved' => 'Réglages des avis enregistrés.',
    ],

];
