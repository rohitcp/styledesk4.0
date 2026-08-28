<?php

declare(strict_types=1);

/* El módulo de Ubicaciones: la cuadrícula, la pantalla de consulta y el formulario. */

return [
    'title' => 'Ubicaciones',
    'intro' => 'Abre una ubicación para ver su responsable, horarios, servicios, personal y ajustes de reserva.',
    'summary' => '{1} :count ubicación activa|[2,*] :count ubicaciones activas',
    'summary_inactive' => ':count inactivas',

    'add' => 'Añadir ubicación',
    'add_first' => 'Añade tu primera ubicación',
    'add_title' => 'Añadir ubicación',
    'add_intro' => 'La dirección, los datos de contacto, el responsable y el horario de la sucursal. Los servicios, la asignación de personal y las reglas de reserva se configuran una vez creada la ubicación.',
    'edit' => 'Editar ubicación',
    'edit_title' => 'Editar ubicación',
    'view' => 'Ver ubicación',
    'deactivate' => 'Desactivar ubicación',
    'activate' => 'Activar ubicación',

    'created' => 'Ubicación creada correctamente.',
    'saved' => 'Ubicación guardada correctamente.',
    'save_failed' => 'No hemos podido guardar los cambios. Revisa la información e inténtalo de nuevo.',
    'correct_fields' => 'Corrige los campos marcados e inténtalo de nuevo.',
    'made_inactive' => ':name ya está inactiva. No admite nuevas reservas; su historial no cambia.',
    'made_active' => ':name vuelve a estar activa.',

    'search_placeholder' => 'Busca por nombre, código, ciudad o dirección',
    'search_label' => 'Buscar ubicaciones',
    'all_statuses' => 'Todos los estados',
    'actions_for' => 'Acciones para :name',

    'empty_title' => 'Todavía no hay ubicaciones.',
    'empty_body' => 'Añade la sucursal desde la que opera tu negocio, para que el personal, los servicios y las reservas tengan dónde ubicarse.',
    'no_matches' => 'Ninguna ubicación coincide con tu búsqueda.',
    'no_matches_hint' => 'Prueba con otra palabra o limpia los filtros.',
    'clear_search' => 'Limpiar búsqueda',

    'inactive_notice' => 'Esta ubicación está inactiva. No admite nuevas reservas y no aparece en la reserva online. Sus citas, transacciones e historial de personal se mantienen.',

    'cards' => [
        'information' => 'Información de la ubicación',
        'address' => 'Dirección',
        'contact' => 'Datos de contacto',
        'contact_hint' => 'Se usan en lugar de los datos generales del negocio siempre que se nombre esta sucursal.',
        'manager' => 'Responsable de la ubicación',
        'manager_hint' => 'Quién es responsable de esta sucursal. Nombrar a alguien aquí no cambia lo que puede hacer en StyleDesk: eso lo decide su rol en Roles y permisos.',
        'manager_hint_short' => 'Quién es responsable de esta sucursal. Esto no cambia lo que puede hacer en StyleDesk.',
        'hours' => 'Horario comercial',
        'hours_hint' => 'Las horas están en la zona horaria de esta ubicación, :timezone.',
        'elsewhere' => 'Se configura en otro sitio',
        'elsewhere_hint' => 'Siguen los ajustes generales del negocio hasta que existan ajustes por ubicación.',
    ],

    'fields' => [
        'name' => 'Nombre de la ubicación',
        'code' => 'Código de ubicación',
        'code_hint' => 'Un nombre corto que distingue esta sucursal en informes y recibos.',
        'type' => 'Tipo de ubicación',
        'primary' => 'Ubicación principal',
        'primary_hint' => 'La sucursal principal del negocio. Marcarla aquí se la quita a la ubicación que la tenga ahora.',
        'status' => 'Estado',
        'status_hint' => 'Una ubicación inactiva no admite nuevas reservas y no aparece en la reserva online. Su historial se conserva.',

        'address_line1' => 'Dirección línea 1',
        'address_line2' => 'Dirección línea 2',
        'suite' => 'Piso / local',
        'city' => 'Ciudad',
        'state' => 'Provincia / estado',
        'postal_code' => 'Código postal',
        'country' => 'País',
        'timezone' => 'Zona horaria',
        'timezone_hint' => 'El horario y las reservas de esta sucursal se leen en esta zona horaria.',

        'manager' => 'Responsable de la ubicación',
        'assistants' => 'Responsables adjuntos',
        'assistants_hint' => 'Quien ya esté elegido arriba como responsable no aparece dos veces.',

        'phone' => 'Teléfono principal',
        'phone_short' => 'Teléfono principal',
        'phone_secondary' => 'Teléfono secundario',
        'email' => 'Correo de la ubicación',
        'booking_email' => 'Correo de contacto para reservas',
        'support_email' => 'Correo de atención al cliente',
        'website' => 'Sitio web',
        'extension' => 'Extensión interna',
        'contact_person' => 'Persona de contacto',

        'contact' => 'Contacto',
        'today' => 'Hoy',
    ],

    'placeholders' => [
        'name' => 'Salón del Centro',
        'code' => 'DT01',
        'type' => 'Sin especificar',
        'country' => 'Elige un país',
        'timezone' => 'Elige una zona horaria',
        'manager' => 'Sin asignar',
    ],

    'not_assigned' => 'Sin asignar',
    'no_phone' => 'Sin teléfono',
    'closed' => 'Cerrado',
    'closed_today' => 'Hoy cerrado',
    'no_active_staff' => 'Todavía no hay personal activo.',
    'no_active_staff_link' => 'Añade un miembro del equipo',
    'no_active_staff_tail' => 'y podrás nombrar aquí a un responsable.',

    'elsewhere' => [
        'holidays' => 'Festivos y horarios especiales',
        'holidays_value' => 'Sigue el horario comercial',
        'staff' => 'Personal asignado',
        'staff_value' => '{1} :count persona tiene esta ubicación como principal|[2,*] :count personas tienen esta ubicación como principal',
        'services' => 'Servicios ofrecidos',
        'services_value' => 'Todos los servicios del negocio',
        'resources' => 'Recursos y salas',
        'resources_value' => 'Todavía sin configurar',
        'booking' => 'Ajustes de reserva',
        'currency' => 'Moneda e idioma',
        'uses_business' => 'Usa los ajustes del negocio',
        'link_hours' => 'Horario comercial →',
        'link_staff' => 'Personal →',
        'link_services' => 'Servicios →',
        'link_business' => 'Negocio →',
        'link_permissions' => 'Permisos →',
    ],

    'confirm' => [
        'deactivate' => '¿Desactivar :name? Dejará de admitir nuevas reservas y no aparecerá en la reserva online. Sus citas, transacciones e historial de personal se conservan.',
        'activate' => '¿Volver a activar :name? Podrá admitir reservas y aparecerá en la reserva online.',
    ],

    'validation' => [
        'name_required' => 'El nombre de la ubicación es obligatorio.',
        'code_unique' => 'Otra ubicación ya usa este código.',
        'status_required' => 'Indica si esta ubicación está activa.',
        'address_required' => 'La dirección línea 1 es obligatoria.',
        'city_required' => 'La ciudad es obligatoria.',
        'state_required' => 'La provincia o el estado es obligatorio.',
        'postal_required' => 'El código postal es obligatorio.',
        'country_required' => 'Elige un país.',
        'country_in' => 'Elige un país de la lista.',
        'timezone_required' => 'Elige una zona horaria.',
        'timezone_in' => 'Elige una zona horaria de la lista.',
        'phone_required' => 'El teléfono principal es obligatorio.',
        'email_required' => 'El correo de la ubicación es obligatorio.',
        'email_invalid' => 'Introduce una dirección de correo válida.',
        'url_invalid' => 'Introduce una URL válida, incluyendo https://',
        'closes_after_opens' => 'La hora de cierre debe ser posterior a la de apertura.',
        'staff_invalid' => 'Elige a uno de tus propios miembros del equipo.',
    ],

    'types' => [
        'salon' => 'Peluquería',
        'spa' => 'Spa',
        'barbershop' => 'Barbería',
        'clinic' => 'Clínica',
        'studio' => 'Estudio',
        'mobile' => 'Móvil',
        'other' => 'Otro',
    ],

    'statuses' => [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],

    'weekdays' => [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ],

    'hours_card' => 'Horario de la ubicación',
    'hours_card_hint' => 'Cuándo abre esta sucursal, en su propia zona horaria. Añade un segundo tramo a los días que cierran a mediodía.',

    'hours_editor' => [
        'copy_monday' => 'Copiar el lunes a mar–vie',
        'open' => 'Abierto',
        'closed' => 'Cerrado',
        'closed_all_day' => 'Cerrado todo el día',
        'add_period' => 'Añadir otro tramo',
        'to' => 'a',
        'remove_period' => 'Quitar este tramo del :day',
        'opening_time' => 'Hora de apertura del :day',
        'closing_time' => 'Hora de cierre del :day',
        'copied' => 'El horario del lunes se ha aplicado de martes a viernes.',
    ],
];
