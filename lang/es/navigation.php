<?php

declare(strict_types=1);

/* La barra de iconos, el cajón de navegación y el menú de cuenta. */

return [
    'bookings' => 'Reservas',
    'menu_for' => 'Menú de :name',
    'dashboard' => 'Panel',
    'calendar' => 'Calendario',
    'clients' => 'Clientes',
    'services' => 'Servicios y recursos',
    'staff' => 'Personal',
    'sales' => 'Ventas',
    'marketing' => 'Marketing',
    'reports' => 'Informes',
    'mentions' => 'Menciones',
    'activity' => 'Actividad',
    'design-system' => 'Sistema de diseño',
    'team' => 'Equipo',
    'app_settings' => 'Configuración',
    'main_menu' => 'Menú principal',
    'search_placeholder' => 'Escribe para buscar y ver recientes…',
    'my_profile' => 'Mi perfil',
    'sign_out' => 'Cerrar sesión',
    'invite_team_members' => 'Invitar al equipo',
    'add' => 'Añadir',

    'active_staff' => '{0} Sin personal activo|{1} :count miembro del personal activo|[2,*] :count miembros del personal activos',
    'coming_soon' => 'Pronto',

    /*
    | Las entradas desplegables bajo los elementos principales del menú.
    |
    | Llevan un `key` en config/navigation.php para que Nav::label() las
    | resuelva aquí. Las entradas vecinas que solo tienen etiqueta nombran
    | pantallas que aún no existen y se dejan sin traducir a propósito.
    */
    'all_bookings' => 'Todas las reservas',
    'booking_leads' => 'Solicitudes de reserva',
    'add_booking' => '+ Añadir reserva',
    'add_walkin' => '+ Añadir sin cita',
    'all_clients' => 'Todos los clientes',
    'add_client' => 'Añadir cliente',
    'coupons_offers' => 'Cupones y ofertas',
    'all_services' => 'Todos los servicios',
    'add_service' => 'Añadir servicio',
    'all_resources' => 'Todos los recursos',
    'add_resource' => 'Añadir recurso',
    'resource_availability' => 'Disponibilidad de recursos',
    'resource_utilization' => 'Uso de recursos',
    'all_staff' => 'Todo el personal',
    'staff_schedule' => 'Horario del personal',
    'shifts' => 'Turnos',
    'staff_utilization' => 'Uso del personal',
    'add_staff' => '+ Añadir personal',
    'email_marketing' => 'Marketing por correo',

    /* Encabezados que solo alcanza un lector de pantalla. */
    'drawer_main' => 'Principal',
    'rail_primary' => 'Principal',
];
