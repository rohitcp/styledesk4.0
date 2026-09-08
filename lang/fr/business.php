<?php

declare(strict_types=1);

/*
| Le module Réglages de l'entreprise : l'écran en lecture seule et son
| formulaire d'édition.
|
| Les libellés de champs sont volontairement partagés par les deux écrans.
| « E-mail principal » désignant une chose sur la page de consultation et une
| autre sur le formulaire, c'est la dérive qu'une clé unique existe pour
| empêcher.
*/

return [
    'title' => 'Entreprise',
    'intro' => 'Nom, type, coordonnées et configuration d’exploitation.',
    'edit' => 'Modifier l’entreprise',
    'edit_title' => 'Modifier l’entreprise',
    'edit_intro' => 'Mettez à jour les informations et les détails d’exploitation. Les établissements, horaires, devises et règles de réservation ont leurs propres pages de réglages.',
    'saved' => 'Réglages de l’entreprise mis à jour.',
    'save_failed' => 'Nous n’avons pas pu enregistrer vos modifications. Réessayez.',
    'correct_fields' => 'Corrigez les champs signalés et réessayez.',

    'cards' => [
        'information' => 'Informations sur l’entreprise',
        'contact' => 'Coordonnées',
        'address' => 'Adresse de l’entreprise',
        'address_hint' => 'Votre adresse principale. :count établissements au total.',
        'regional' => 'Réglages régionaux',
        'regional_hint' => 'Configurés dans leurs propres modules ; affichés ici pour le contexte.',
        'security' => 'Sécurité',
        'defaults' => 'Valeurs par défaut',
        'defaults_hint' => 'Points de départ pour les nouvelles réservations et prestations.',
        'presence' => 'Présence de l’entreprise',
        'presence_hint' => 'Où vos clients vous trouvent en dehors de StyleDesk.',
        'advanced' => 'Informations avancées',
        'payments' => 'Encaissement',
        'payments_hint' => 'Les comptes vers lesquels on demande aux clients d’envoyer l’argent. Lus à voix haute en caisse, ils sont donc conservés exactement tels que vous les écrivez.',
    ],

    'fields' => [
        'name' => 'Nom de l’entreprise',
        'legal_name' => 'Raison sociale',
        'business_type' => 'Type d’entreprise',
        'category' => 'Catégorie / spécialisation',
        'description' => 'Description',
        'logo' => 'Logo de l’entreprise',
        'status' => 'Statut',

        'business_email' => 'E-mail principal',
        'business_phone' => 'Téléphone principal',
        'support_email' => 'E-mail d’assistance',
        'booking_email' => 'E-mail de contact réservations',
        'website' => 'Site web',
        'website_scheme' => 'Protocole de l’URL',

        'address_line1' => 'Adresse ligne 1',
        'address_line2' => 'Adresse ligne 2',
        'city' => 'Ville',
        'state' => 'Région / département',
        'postal_code' => 'Code postal',
        'country' => 'Pays',
        'address' => 'Adresse',

        'primary_language' => 'Langue principale',
        'secondary_languages' => 'Langues secondaires',
        'primary_currency' => 'Devise principale',
        'secondary_currencies' => 'Devises secondaires',
        'timezone' => 'Fuseau horaire',
        'date_format' => 'Format de date',
        'time_format' => 'Format d’heure',
        'first_day_of_week' => 'Premier jour de la semaine',

        'default_location' => 'Établissement par défaut',
        'default_booking_duration' => 'Durée de réservation par défaut',
        'default_appointment_interval' => 'Intervalle de rendez-vous par défaut',
        'default_tax_behavior' => 'Traitement de la TVA par défaut',
        'default_tax_rate' => 'Taux de TVA',
        'paypal_handle' => 'PayPal',
        'zelle_handle' => 'Zelle',
        'cash_app_handle' => 'Cash App',
        'venmo_handle' => 'Venmo',
        'session_timeout' => 'Expiration de session',
        'default_staff_assignment' => 'Affectation par défaut',
        'allow_online_booking' => 'Autoriser la réservation en ligne',
        'guest_booking' => 'Réservation sans compte activée',

        'business_id' => 'Identifiant de l’entreprise',
        'booking_address' => 'Adresse de réservation',

        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google_business' => 'Fiche d’établissement Google',
    ],

    'manage' => 'Gérer',
    'manage_branding' => 'Identité visuelle →',
    'manage_locations' => 'Gérer les établissements →',
    'enabled' => 'Activé',
    'disabled' => 'Désactivé',

    /*
     * Les messages de validation.
     *
     * Écrits comme des consignes plutôt que comme la description d'une règle.
     * « Le champ e-mail doit être une adresse valide » nomme le validateur ;
     * « Saisissez une adresse e-mail valide » dit quoi faire, et cela se lit à
     * quelques centimètres du champ concerné.
     */
    'validation' => [
        'name_required' => 'Le nom de l’entreprise est obligatoire.',
        'email_required' => 'L’e-mail principal est obligatoire.',
        'email_invalid' => 'Saisissez une adresse e-mail valide.',
        'url_invalid' => 'Saisissez une URL de site valide, avec https://',
        'instagram_invalid' => 'Saisissez une URL Instagram valide, avec https://',
        'facebook_invalid' => 'Saisissez une URL Facebook valide, avec https://',
        'tiktok_invalid' => 'Saisissez une URL TikTok valide, avec https://',
        'google_invalid' => 'Saisissez une URL Google Business valide, avec https://',
        'status_required' => 'Indiquez si l’entreprise est active.',
        'location_invalid' => 'Choisissez l’un de vos propres établissements.',
    ],

    /*
     * Les valeurs dans les listes déroulantes, pas seulement leurs libellés.
     *
     * §« Cette approche doit aussi s'appliquer aux valeurs des listes » — un
     * formulaire aux libellés espagnols et aux options anglaises est le même
     * écran à moitié traduit, déplacé d'un niveau.
     *
     * Les noms de *format* de date et d'heure restent tels quels :
     * « DD/MM/YYYY » est un motif, pas une phrase, et traduire les lettres
     * décrirait un format que personne n'utilise.
     */
    'first_day_of_week' => [
        '0' => 'Dimanche',
        '1' => 'Lundi',
        '6' => 'Samedi',
    ],

    'time_formats' => [
        '12' => '12 heures (1:30 PM)',
        '24' => '24 heures (13:30)',
    ],

    'durations' => [
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'hour' => '1 heure',
        'hour_thirty' => '1 heure 30 minutes',
        'hours' => ':count heures',
    ],

    'tax_behaviors' => [
        'inclusive' => 'Prix TTC',
        'exclusive' => 'TVA ajoutée à l’encaissement',
        'none' => 'Aucune TVA appliquée',
    ],

    'staff_assignment' => [
        'any' => 'N’importe quel praticien disponible',
        'client-chooses' => 'Le client choisit un praticien',
        'manual' => 'Affecté manuellement par l’entreprise',
    ],

    'hints' => [
        'tax_rate' => 'Un pourcentage. Appliqué aux totaux de réservation soumis à la TVA.',
        'inactive' => 'Une entreprise inactive n’apparaît pas dans la réservation publique.',
        'session_timeout' => 'Déconnecte automatiquement après une période d’inactivité.',
        'interval' => 'La grille sur laquelle les heures de début se calent.',
        'regional' => 'Langues, devises et fuseau horaire se règlent dans leurs propres modules.',
    ],

    'placeholders' => [
        'legal_name' => 'Telle qu’enregistrée, si différente du nom commercial',
        'category' => 'Spécialistes du cheveu bouclé',
        'description' => 'Une phrase que vos clients liront sur votre page de réservation.',
        'paypal_handle' => 'paypal.me/votresalon',
        'zelle_handle' => 'paiement@votresalon.com',
        'cash_app_handle' => '$votresalon',
        'venmo_handle' => '@votresalon',
    ],

    'choose' => [
        'date_format' => 'Choisissez un format de date',
        'time_format' => 'Choisissez un format d’heure',
        'day' => 'Choisissez un jour',
        'duration' => 'Choisissez une durée',
        'interval' => 'Choisissez un intervalle',
        'tax' => 'Choisissez le traitement de la TVA',
        'session_timeout' => 'Choisissez un délai',
        'assignment' => 'Choisissez une règle d’affectation',
    ],

    /*
     * La liste des types d'entreprise.
     *
     * Des données de référence propres à StyleDesk, indexées par le slug que le
     * seeder attribue — ce n'est pas quelque chose qu'une entreprise a saisi,
     * c'est donc à nous de le traduire. Tout ce qu'une entreprise a écrit
     * elle-même reste dans ses propres mots.
     *
     * Le nom en base sert de repli : un type ajouté par un seeder ultérieur
     * sans traduction s'affiche tel quel.
     */
    'types' => [
        'hair-salon' => 'Salon de coiffure',
        'barber-shop' => 'Barbier',
        'nail-salon' => 'Onglerie',
        'spa' => 'Spa',
        'massage' => 'Massage',
        'med-spa' => 'Med spa',
        'esthetics' => 'Esthétique',
        'eyebrows-lashes' => 'Sourcils et cils',
        'makeup-studio' => 'Studio de maquillage',
        'tattoo-studio' => 'Studio de tatouage',
        'wellness' => 'Bien-être',
        'fitness' => 'Fitness',
        'other' => 'Autre',
    ],

    'session_timeouts' => [
        15 => '15 minutes',
        30 => '30 minutes (recommandé)',
        60 => '1 heure',
        120 => '2 heures',
        240 => '4 heures',
        480 => '8 heures',
    ],
];
