<?php

declare(strict_types=1);

/* Las etiquetas del catálogo del módulo de Clientes. */

return [
    'fields' => [
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'mobile' => 'Número de móvil',
        'email' => 'Correo electrónico',
        'date_of_birth' => 'Fecha de nacimiento',
        'gender' => 'Género',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'state' => 'Provincia / estado',
        'postal_code' => 'Código postal',
        'country' => 'País',
        'preferred_location' => 'Ubicación preferida',
        'preferred_staff' => 'Profesional preferido',
        'avatar' => 'Foto de perfil',
        'notes' => 'Notas',
    ],

    'name_formats' => [
        'first_last' => 'Nombre y luego apellidos',
        'last_first' => 'Apellidos y luego nombre',
        'first_initial' => 'Nombre e inicial del apellido',
        'preferred_last' => 'Nombre preferido y luego apellidos',
    ],

    'statuses' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'archived' => 'Archivado',
    ],

    'default_statuses' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'communication_methods' => [
        'email' => 'Correo',
        'sms' => 'SMS',
        'phone' => 'Teléfono',
        'none' => 'Sin preferencia',
    ],

    'marketing_defaults' => [
        'ask' => 'Preguntar al cliente',
        'in' => 'Ha dado su consentimiento',
        'out' => 'No lo ha dado',
    ],

    'duplicate_rules' => [
        'email' => 'El mismo correo electrónico',
        'mobile' => 'El mismo número de móvil',
        'name_mobile' => 'El mismo nombre, apellidos y número de móvil',
    ],

    'search_fields' => [
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'mobile' => 'Número de móvil',
        'email' => 'Correo electrónico',
        'client_id' => 'ID de cliente',
    ],

    'creation_sources' => [
        'client_list' => 'Lista de clientes',
        'booking' => 'Pantalla de reserva',
        'calendar' => 'Calendario',
        'walk_in' => 'Reserva sin cita',
        'pos' => 'Punto de venta',
    ],

    'booking_panels' => [
        'preferred_staff' => 'Profesional preferido',
        'preferred_location' => 'Ubicación preferida',
        'preferences' => 'Preferencias del cliente',
        'notes' => 'Notas importantes',
        'last_booking' => 'Última reserva',
        'recent_visits' => 'Visitas recientes',
        'recent_staff' => 'Profesionales recientes',
        'rating' => 'Valoración media del cliente',
        'book_same_again' => 'Reservar lo mismo otra vez',
    ],

    'history_panels' => [
        'upcoming' => 'Próximas citas',
        'previous' => 'Citas anteriores',
        'cancelled' => 'Citas canceladas',
        'no_shows' => 'Ausencias',
        'services' => 'Servicios reservados anteriormente',
        'staff' => 'Profesionales que le han atendido',
        'locations' => 'Ubicaciones visitadas',
        'notes' => 'Notas del cliente',
        'preferences' => 'Preferencias',
        'activity' => 'Actividad de reservas',
    ],

    'tag_colors' => [
        'slate' => 'Pizarra',
        'violet' => 'Violeta',
        'blue' => 'Azul',
        'teal' => 'Verde azulado',
        'green' => 'Verde',
        'amber' => 'Ámbar',
        'rose' => 'Rosa',
        'plum' => 'Ciruela',
    ],
];
