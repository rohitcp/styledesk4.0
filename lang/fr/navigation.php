<?php

declare(strict_types=1);

/*
| La barre d'icônes, le tiroir et le menu du compte.
|
| C'est une couche de traduction, pas un renommage : une valeur qui différerait
| changerait la copie du produit sous couvert d'ajouter une langue.
|
| Les clés reflètent le `key` de config/navigation.php — Nav::label() résout
| navigation.<key>, les deux ne peuvent donc pas diverger.
*/

return [
    'bookings' => 'Réservations',
    'menu_for' => 'Menu :name',
    'dashboard' => 'Tableau de bord',
    'calendar' => 'Agenda',
    'clients' => 'Clients',
    'services' => 'Prestations et ressources',
    'staff' => 'Personnel',
    'sales' => 'Ventes',
    'marketing' => 'Marketing',
    'reports' => 'Rapports',
    'mentions' => 'Mentions',
    'activity' => 'Activité',
    'design-system' => 'Système de design',
    'team' => 'Équipe',
    'app_settings' => 'Réglages',
    'main_menu' => 'Menu principal',
    'search_placeholder' => 'Tapez pour rechercher et voir les éléments récents…',
    'my_profile' => 'Mon profil',
    'sign_out' => 'Se déconnecter',
    'invite_team_members' => 'Inviter l’équipe',
    'add' => 'Ajouter',

    'active_staff' => '{0} Aucun membre actif|{1} :count membre actif|[2,*] :count membres actifs',
    'coming_soon' => 'Bientôt',

    /*
    | Les entrées déroulantes sous les éléments principaux de la barre.
    |
    | Toutes portent un `key` dans config/navigation.php pour que Nav::label()
    | les résolve ici, y compris celles dont l'écran n'est pas encore construit :
    | elles sont dans le menu et se lisent, donc un menu à moitié traduit est le
    | même défaut, que le lien mène quelque part ou non.
    */
    'all_bookings' => 'Toutes les réservations',
    'booking_leads' => 'Demandes de réservation',
    'add_booking' => '+ Ajouter une réservation',
    'add_walkin' => '+ Ajouter un sans rendez-vous',
    'all_clients' => 'Tous les clients',
    'add_client' => 'Ajouter un client',
    'coupons_offers' => 'Bons et offres',
    'all_services' => 'Toutes les prestations',
    'add_service' => 'Ajouter une prestation',
    'all_resources' => 'Toutes les ressources',
    'add_resource' => 'Ajouter une ressource',
    'resource_availability' => 'Disponibilité des ressources',
    'resource_utilization' => 'Utilisation des ressources',
    'all_staff' => 'Tout le personnel',
    'staff_schedule' => 'Planning du personnel',
    'shifts' => 'Services',
    'staff_utilization' => 'Taux d’occupation',
    'add_staff' => '+ Ajouter un membre',
    'email_marketing' => 'Marketing par e-mail',
    'sms_marketing' => 'Marketing par SMS',
    'social_marketing' => 'Marketing sur les réseaux sociaux',
    'review_marketing' => 'Marketing des avis Google',
    'gift_cards' => 'Cartes cadeaux',
    'loyalty' => 'Fidélité',
    'groups' => 'Groupes',
    'forms_waivers' => 'Formulaires et décharges',
    'membership' => 'Abonnement',

    /* Les titres au-dessus d'un groupe d'entrées dans un menu ouvert. */
    'sections' => [
        'management' => 'Gestion',
        'quick_actions' => 'Actions rapides',
        'services' => 'Prestations',
        'resources' => 'Ressources',
        'channels' => 'Canaux',
    ],

    /* Noms accessibles, lorsqu'ils diffèrent du libellé à côté. */
    'aria' => [
        'services' => 'Prestations et ressources',
    ],

    /* Titres que seul un lecteur d'écran atteint. */
    'drawer_main' => 'Principal',
    'rail_primary' => 'Principal',
];
