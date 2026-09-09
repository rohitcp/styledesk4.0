<?php

declare(strict_types=1);

/*
| Les listes d'options du module Personnel.
|
| Les clés sont la valeur enregistrée en base, si bien que la liste déroulante
| et la règle de validation lisent toutes deux la même liste dans
| config/staff.php, tandis que chaque langue décide du nom des options.
|
| Les pronoms sont l'exception à signaler : « she/her » ne se traduit pas mot à
| mot, c'est la façon dont une personne se désigne elle-même. Chaque langue
| énonce donc son propre jeu plutôt que de calquer la grammaire anglaise.
*/

return [
    'employment_types' => [
        'full-time' => 'Salarié à temps plein',
        'part-time' => 'Salarié à temps partiel',
        'contractor' => 'Prestataire',
        'independent' => 'Praticien indépendant',
        'commission' => 'À la commission',
        'booth-renter' => 'Location de fauteuil',
        'freelancer' => 'Freelance',
        'temporary' => 'Personnel temporaire',
        'apprentice' => 'Apprenti / en formation',
        'intern' => 'Stagiaire',
        'volunteer' => 'Bénévole',
        'other' => 'Autre',
    ],

    'provider_types' => [
        'service-provider' => 'Praticien',
        'non-provider' => 'Personnel sans prestation',
        'manager-provider' => 'Responsable et praticien',
        'front-desk' => 'Accueil / réception',
        'administrative' => 'Personnel administratif',
        'support' => 'Personnel de soutien',
    ],

    'specialities' => [
        'hair-stylist' => 'Coiffeur',
        'barber' => 'Barbier',
        'colourist' => 'Coloriste',
        'nail-technician' => 'Prothésiste ongulaire',
        'massage-therapist' => 'Masseur',
        'esthetician' => 'Esthéticien',
        'makeup-artist' => 'Maquilleur',
        'lash-technician' => 'Technicien cils',
        'brow-specialist' => 'Spécialiste sourcils',
        'therapist' => 'Thérapeute',
        'consultant' => 'Conseiller',
    ],

    'pronouns' => [
        'she/her' => 'elle',
        'he/him' => 'il',
        'they/them' => 'iel',
        'she/they' => 'elle / iel',
        'he/they' => 'il / iel',
        'prefer-not-to-say' => 'Préfère ne pas le préciser',
    ],

    'phone_types' => [
        'mobile' => 'Mobile',
        'work' => 'Professionnel',
        'home' => 'Domicile',
        'other' => 'Autre',
    ],

    'statuses' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'on-leave' => 'En congé',
        'pending-invite' => 'Invitation en attente',
        'invite-queued' => 'Invitation en file d’attente',
        'invite-failed' => 'Échec de l’invitation',
        'invite-expired' => 'Invitation expirée',
        'suspended' => 'Suspendu',
        'archived' => 'Archivé',
    ],

    'sorts' => [
        'name' => 'Nom',
        'recent' => 'Ajout récent',
        'role' => 'Rôle',
        'location' => 'Établissement',
        'status' => 'Statut',
    ],
];
