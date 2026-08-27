<?php

declare(strict_types=1);

/* El módulo de Negocio: la pantalla de consulta y su formulario de edición. */

return [
    'title' => 'Negocio',
    'intro' => 'Nombre del negocio, tipo, datos de contacto y configuración operativa.',
    'edit' => 'Editar negocio',
    'edit_title' => 'Editar negocio',
    'edit_intro' => 'Actualiza la información y los datos operativos de tu negocio. Las ubicaciones, los horarios, las monedas y las reglas de reserva tienen sus propias páginas de ajustes.',
    'saved' => 'Configuración del negocio actualizada correctamente.',
    'save_failed' => 'No hemos podido guardar los cambios. Inténtalo de nuevo.',
    'correct_fields' => 'Corrige los campos marcados e inténtalo de nuevo.',

    'cards' => [
        'information' => 'Información del negocio',
        'contact' => 'Datos de contacto',
        'address' => 'Dirección del negocio',
        'address_hint' => 'Tu dirección principal. :count ubicaciones en total.',
        'regional' => 'Ajustes regionales',
        'regional_hint' => 'Se configuran en sus propios módulos; aquí se muestran como referencia.',
        'defaults' => 'Valores predeterminados',
        'defaults_hint' => 'Punto de partida para las nuevas reservas y servicios.',
        'presence' => 'Presencia del negocio',
        'presence_hint' => 'Dónde te encuentran tus clientes fuera de StyleDesk.',
        'advanced' => 'Información avanzada',
    ],

    'fields' => [
        'name' => 'Nombre del negocio',
        'legal_name' => 'Razón social',
        'business_type' => 'Tipo de negocio',
        'category' => 'Categoría / especialidad',
        'description' => 'Descripción',
        'logo' => 'Logotipo del negocio',
        'status' => 'Estado',

        'business_email' => 'Correo principal',
        'business_phone' => 'Teléfono principal',
        'support_email' => 'Correo de soporte',
        'booking_email' => 'Correo de contacto para reservas',
        'website' => 'Sitio web',

        'address_line1' => 'Dirección línea 1',
        'address_line2' => 'Dirección línea 2',
        'city' => 'Ciudad',
        'state' => 'Provincia / estado',
        'postal_code' => 'Código postal',
        'country' => 'País',
        'address' => 'Dirección',

        'primary_language' => 'Idioma principal',
        'secondary_languages' => 'Idiomas secundarios',
        'primary_currency' => 'Moneda principal',
        'secondary_currencies' => 'Monedas secundarias',
        'timezone' => 'Zona horaria',
        'date_format' => 'Formato de fecha',
        'time_format' => 'Formato de hora',
        'first_day_of_week' => 'Primer día de la semana',

        'default_location' => 'Ubicación predeterminada',
        'default_booking_duration' => 'Duración de reserva predeterminada',
        'default_appointment_interval' => 'Intervalo de citas predeterminado',
        'default_tax_behavior' => 'Tratamiento fiscal predeterminado',
        'default_staff_assignment' => 'Asignación de personal predeterminada',
        'allow_online_booking' => 'Permitir reserva online',
        'guest_booking' => 'Reserva de invitados activada',

        'business_id' => 'ID del negocio',
        'booking_address' => 'Dirección de reservas',

        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google_business' => 'Perfil de Google Business',
    ],

    'manage' => 'Gestionar',
    'manage_branding' => 'Identidad de marca →',
    'manage_locations' => 'Gestionar ubicaciones →',
    'enabled' => 'Activado',
    'disabled' => 'Desactivado',

    'validation' => [
        'name_required' => 'El nombre del negocio es obligatorio.',
        'email_required' => 'El correo principal es obligatorio.',
        'email_invalid' => 'Introduce una dirección de correo válida.',
        'url_invalid' => 'Introduce una URL válida, incluyendo https://',
        'instagram_invalid' => 'Introduce una URL de Instagram válida, incluyendo https://',
        'facebook_invalid' => 'Introduce una URL de Facebook válida, incluyendo https://',
        'tiktok_invalid' => 'Introduce una URL de TikTok válida, incluyendo https://',
        'google_invalid' => 'Introduce una URL de Google Business válida, incluyendo https://',
        'status_required' => 'Indica si el negocio está activo.',
        'location_invalid' => 'Elige una de tus propias ubicaciones.',
    ],

    'first_day_of_week' => [
        '0' => 'Domingo',
        '1' => 'Lunes',
        '6' => 'Sábado',
    ],

    'time_formats' => [
        '12' => '12 horas (1:30 PM)',
        '24' => '24 horas (13:30)',
    ],

    'durations' => [
        'minutes' => '{1} :count minuto|[2,*] :count minutos',
        'hour' => '1 hora',
        'hour_thirty' => '1 hora 30 minutos',
        'hours' => ':count horas',
    ],

    'tax_behaviors' => [
        'inclusive' => 'Los precios incluyen impuestos',
        'exclusive' => 'Impuestos añadidos al pagar',
        'none' => 'Sin impuestos',
    ],

    'staff_assignment' => [
        'any' => 'Cualquier miembro del equipo disponible',
        'client-chooses' => 'El cliente elige a la persona',
        'manual' => 'Asignado manualmente por el negocio',
    ],

    'hints' => [
        'inactive' => 'Un negocio inactivo no aparece en la reserva pública.',
        'interval' => 'La cuadrícula a la que se ajustan las horas de inicio.',
        'regional' => 'Los idiomas, las monedas y la zona horaria se definen en sus propios módulos.',
    ],

    'placeholders' => [
        'legal_name' => 'Tal y como está registrada, si difiere del nombre comercial',
        'category' => 'Especialistas en pelo rizado',
        'description' => 'Una frase que tus clientes leerán en tu página de reservas.',
    ],

    'choose' => [
        'date_format' => 'Elige un formato de fecha',
        'time_format' => 'Elige un formato de hora',
        'day' => 'Elige un día',
        'duration' => 'Elige una duración',
        'interval' => 'Elige un intervalo',
        'tax' => 'Elige el tratamiento fiscal',
        'assignment' => 'Elige una regla de asignación',
    ],

    'types' => [
        'hair-salon' => 'Peluquería',
        'barber-shop' => 'Barbería',
        'nail-salon' => 'Salón de uñas',
        'spa' => 'Spa',
        'massage' => 'Masaje',
        'med-spa' => 'Medicina estética',
        'esthetics' => 'Estética',
        'eyebrows-lashes' => 'Cejas y pestañas',
        'makeup-studio' => 'Estudio de maquillaje',
        'tattoo-studio' => 'Estudio de tatuajes',
        'wellness' => 'Bienestar',
        'fitness' => 'Fitness',
        'other' => 'Otro',
    ],
];
