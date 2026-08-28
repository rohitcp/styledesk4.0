<?php

declare(strict_types=1);

/* El módulo de Identidad de marca: el formulario y sus vistas previas. */

return [
    'title' => 'Identidad de marca',
    'intro' => 'Tu logotipo, favicon y colores, usados en StyleDesk, tu página de reservas, los correos de confirmación y recordatorio, los recibos y las facturas.',
    'saved' => 'Identidad de marca actualizada correctamente.',
    'save_failed' => 'No hemos podido guardar tu identidad de marca. Inténtalo de nuevo.',
    'reset_done' => 'Identidad de marca restablecida a la de StyleDesk.',
    'correct_fields' => 'Corrige los campos marcados e inténtalo de nuevo.',

    'reset' => 'Restablecer la identidad predeterminada',
    'remove_confirm' => '¿Quitar esta imagen? Se elimina al guardar.',
    'reset_confirm' => '¿Restablecer la identidad de marca a la de StyleDesk? Se eliminarán tu logotipo, tu favicon y tus colores.',

    'logo' => [
        'title' => 'Logotipo del negocio',
        'hint' => 'PNG, JPG, SVG o WEBP, hasta 2 MB. Unos 400×120 px funcionan bien. El fondo transparente se conserva.',
        'upload' => 'Subir logotipo',
        'replace' => 'Cambiar logotipo',
    ],

    'favicon' => [
        'title' => 'Favicon / icono de la app',
        'hint' => 'PNG, SVG o ICO, hasta 2 MB. Usa una imagen cuadrada: 512×512 px es lo ideal.',
        'upload' => 'Subir favicon',
        'replace' => 'Cambiar favicon',
    ],

    'upload' => [
        'uploading' => 'Subiendo…',
        'removed' => 'Eliminado. Guarda para confirmar.',
        'failed' => 'No se ha podido subir ese archivo.',
        'mimes' => 'Usa un archivo :formats.',
        'too_large' => 'El archivo debe ocupar 2 MB o menos.',
    ],

    'colours' => [
        'title' => 'Colores de marca',
        'hint' => 'Elige un color o escribe un valor hexadecimal. El tono de hover y el color del texto de tus botones se calculan a partir de estos.',
        'primary' => 'Principal',
        'primary_hint' => 'Botones, enlaces y la barra superior.',
        'secondary' => 'Secundario',
        'secondary_hint' => 'Destacados de apoyo.',
        'accent' => 'Acento',
        'accent_hint' => 'Etiquetas y pequeños énfasis.',
        'picker_label' => 'Selector de color :name',
        'invalid' => 'Introduce un color hexadecimal, como #3d348b.',
        'contrast' => 'Texto blanco sobre tu color principal:',
        'contrast_warning' => 'Tu barra superior, la cabecera de la página de reservas y la de los correos imprimen texto blanco sobre este color, y con este tono cuesta leerlo. Un color más oscuro suele solucionarlo.',
        'required_primary' => 'Elige un color principal.',
        'required_secondary' => 'Elige un color secundario.',
        'required_accent' => 'Elige un color de acento.',
    ],

    'grades' => [
        'aaa' => 'AAA',
        'aa' => 'AA',
        'large' => 'Solo texto grande',
        'fails' => 'No cumple',
    ],

    'preview' => [
        'title' => 'Vista previa',
        'hint' => 'Se actualiza a medida que cambias los colores de arriba.',
        'trial' => 'Te quedan 0 días de prueba gratuita',
        'in_app' => 'En StyleDesk',
        'booking_page' => 'Tu página de reservas',
        'emails' => 'Correos de confirmación, recordatorio e invitación',
        'receipts' => 'Recibos y facturas',

        'your_logo' => 'Tu logotipo',
        'book_appointment' => 'Reservar cita',
        'confirmed' => 'Confirmada',
        'new' => 'Nuevo',
        'link_sentence' => 'Un enlace se ve así: :link.',
        'this_one' => 'este',

        'book_online' => 'Reserva online, cuando quieras',
        'select' => 'Elegir',
        'continue' => 'Continuar',
        'minutes' => ':count min',

        'email_subject' => 'Tu cita está confirmada',
        'email_body' => 'Jueves 4 de septiembre, 14:00, con Priya en :business.',
        'view_appointment' => 'Ver la cita',
        'email_footer' => 'Enviado por :business a través de StyleDesk',

        'receipt' => 'Recibo',
        'tax' => 'Impuestos',
        'total' => 'Total',

        'where_used' => 'Dónde se usa',
        'where_used_body' => 'La aplicación de StyleDesk, tus páginas de reserva, las confirmaciones de cita, los recordatorios, las invitaciones al equipo, los recibos, las facturas, las tarjetas regalo y las notificaciones a clientes.',
        'representations' => 'Los paneles de arriba son representaciones, no las plantillas reales.',
    ],
];
