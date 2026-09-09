<?php

declare(strict_types=1);

/*
| Le module Identité visuelle : le formulaire et ses aperçus.
|
| Les panneaux d'aperçu représentent l'application, la page de réservation, un
| e-mail et un reçu — les mots qu'ils contiennent sont donc du contenu
| d'exemple, et ils sont traduits pour la même raison que le reste de l'écran :
| un lecteur espagnol regardant ce que son identité visuelle va produire doit
| pouvoir le lire.
*/

return [
    'title' => 'Identité visuelle',
    'intro' => 'Votre logo, votre favicon et vos couleurs, utilisés dans StyleDesk, sur votre page de réservation, dans les e-mails de confirmation et de rappel, sur les reçus et les factures.',
    'saved' => 'Réglages d’identité visuelle mis à jour.',
    'save_failed' => 'Nous n’avons pas pu enregistrer votre identité visuelle. Réessayez.',
    'reset_done' => 'Identité visuelle rétablie à celle de StyleDesk par défaut.',
    'correct_fields' => 'Corrigez les champs signalés et réessayez.',

    'reset' => 'Rétablir l’identité visuelle par défaut',
    'remove_confirm' => 'Retirer cette image ? Elle sera supprimée à l’enregistrement.',
    'reset_confirm' => 'Rétablir l’identité visuelle StyleDesk par défaut ? Votre logo, votre favicon et vos couleurs seront retirés.',

    'logo' => [
        'title' => 'Logo de l’entreprise',
        'hint' => 'PNG, JPG, SVG ou WEBP, jusqu’à 2 Mo. Environ 400 × 120 px fonctionne bien. Un fond transparent est conservé tel quel.',
        'upload' => 'Envoyer un logo',
        'replace' => 'Remplacer le logo',
    ],

    'favicon' => [
        'title' => 'Favicon / icône d’application',
        'hint' => 'PNG, SVG ou ICO, jusqu’à 2 Mo. Utilisez une image carrée — 512 × 512 px est idéal.',
        'upload' => 'Envoyer un favicon',
        'replace' => 'Remplacer le favicon',
    ],

    'upload' => [
        'uploading' => 'Envoi en cours…',
        'removed' => 'Retiré. Enregistrez pour confirmer.',
        'failed' => 'Ce fichier n’a pas pu être envoyé.',
        'mimes' => 'Utilisez un fichier :formats.',
        'too_large' => 'Le fichier doit faire 2 Mo ou moins.',
    ],

    'colours' => [
        'title' => 'Couleurs de la marque',
        'hint' => 'Choisissez une couleur ou saisissez une valeur hexadécimale. La nuance au survol et la couleur du texte sur vos boutons en sont déduites.',
        'primary' => 'Principale',
        'primary_hint' => 'Boutons, liens et barre d’application.',
        'secondary' => 'Secondaire',
        'secondary_hint' => 'Accents d’appui.',
        'accent' => 'Accent',
        'accent_hint' => 'Pastilles et petites mises en valeur.',
        'picker_label' => 'Sélecteur de couleur :name',
        'invalid' => 'Saisissez une couleur hexadécimale, comme #3d348b.',
        'contrast' => 'Texte blanc sur votre couleur principale :',
        'contrast_warning' => 'Votre barre d’application, l’en-tête de votre page de réservation et celui de vos e-mails impriment du texte blanc sur cette couleur, et à cette nuance il est difficile à lire. Une couleur plus foncée règle généralement le problème.',
        'required_primary' => 'Choisissez une couleur principale.',
        'required_secondary' => 'Choisissez une couleur secondaire.',
        'required_accent' => 'Choisissez une couleur d’accent.',
    ],

    /*
     * Les niveaux WCAG. Laissés sous leur forme standard : « AA » est le nom
     * d'un niveau de conformité, pas un mot anglais, et un designer qui le
     * cherche dans une spécification cherche ces deux lettres.
     */
    'grades' => [
        'aaa' => 'AAA',
        'aa' => 'AA',
        'large' => 'Grands textes uniquement',
        'fails' => 'Échec',
    ],

    'preview' => [
        'title' => 'Aperçu',
        'hint' => 'Se met à jour à mesure que vous changez les couleurs ci-dessus.',
        'trial' => 'Il reste 0 jour d’essai gratuit',
        'in_app' => 'Dans StyleDesk',
        'booking_page' => 'Votre page de réservation',
        'emails' => 'E-mails de confirmation, de rappel et d’invitation',
        'receipts' => 'Reçus et factures',

        'your_logo' => 'Votre logo',
        'book_appointment' => 'Prendre rendez-vous',
        'confirmed' => 'Confirmé',
        'new' => 'Nouveau',
        'link_sentence' => 'Un lien ressemble à :link.',
        'this_one' => 'celui-ci',

        'book_online' => 'Réservez en ligne, à tout moment',
        'select' => 'Sélectionner',
        'continue' => 'Continuer',
        'minutes' => ':count min',

        'email_subject' => 'Votre rendez-vous est confirmé',
        'email_body' => 'Jeudi 4 septembre, 14h00 avec Priya chez :business.',
        'view_appointment' => 'Voir le rendez-vous',
        'email_footer' => 'Envoyé par :business via StyleDesk',

        'receipt' => 'Reçu',
        'tax' => 'TVA',
        'total' => 'Total',

        'where_used' => 'Où cela sert',
        'where_used_body' => 'L’application StyleDesk, vos pages de réservation, les confirmations de rendez-vous, les rappels, les invitations d’équipe, les reçus, les factures, les cartes cadeaux et les notifications clients.',
        'representations' => 'Les panneaux ci-dessus sont des représentations, pas les modèles eux-mêmes.',
    ],
];
