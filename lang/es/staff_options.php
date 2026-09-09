<?php

declare(strict_types=1);

/* Las listas de opciones del módulo de Personal. */

return [
    'employment_types' => [
        'full-time' => 'Empleado a tiempo completo',
        'part-time' => 'Empleado a tiempo parcial',
        'contractor' => 'Contratista',
        'independent' => 'Profesional independiente',
        'commission' => 'A comisión',
        'booth-renter' => 'Alquiler de sillón',
        'freelancer' => 'Autónomo',
        'temporary' => 'Personal temporal',
        'apprentice' => 'Aprendiz / en formación',
        'intern' => 'Becario',
        'volunteer' => 'Voluntario',
        'other' => 'Otro',
    ],

    'provider_types' => [
        'service-provider' => 'Presta servicios',
        'non-provider' => 'No presta servicios',
        'manager-provider' => 'Responsable y presta servicios',
        'front-desk' => 'Recepción',
        'administrative' => 'Personal administrativo',
        'support' => 'Personal de apoyo',
    ],

    'specialities' => [
        'hair-stylist' => 'Estilista',
        'barber' => 'Barbero',
        'colourist' => 'Colorista',
        'nail-technician' => 'Técnico de uñas',
        'massage-therapist' => 'Masajista',
        'esthetician' => 'Esteticista',
        'makeup-artist' => 'Maquillador',
        'lash-technician' => 'Técnico de pestañas',
        'brow-specialist' => 'Especialista en cejas',
        'therapist' => 'Terapeuta',
        'consultant' => 'Asesor',
    ],

    'pronouns' => [
        'she/her' => 'ella',
        'he/him' => 'él',
        'they/them' => 'elle',
        'she/they' => 'ella / elle',
        'he/they' => 'él / elle',
        'prefer-not-to-say' => 'Prefiero no decirlo',
    ],

    'phone_types' => [
        'mobile' => 'Móvil',
        'work' => 'Trabajo',
        'home' => 'Casa',
        'other' => 'Otro',
    ],

    'statuses' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'on-leave' => 'De permiso',
        'pending-invite' => 'Invitación pendiente',
        'invite-queued' => 'Invitación en cola',
        'invite-failed' => 'Invitación fallida',
        'invite-expired' => 'Invitación caducada',
        'suspended' => 'Suspendido',
        'archived' => 'Archivado',
    ],

    'sorts' => [
        'name' => 'Nombre',
        'recent' => 'Añadidos recientemente',
        'role' => 'Rol',
        'location' => 'Ubicación',
        'status' => 'Estado',
    ],
];
