<?php

declare(strict_types=1);

/*
| Las palabras que usa toda la aplicación.
*/

return [
    'retry' => 'Reintentar',
    'save' => 'Guardar',
    'save_changes' => 'Guardar cambios',
    'done' => 'Hecho',
    'cancel' => 'Cancelar',
    'edit' => 'Editar',
    /** The accessible name for a card's Edit control; :section is the card title. */
    'edit_section' => 'Editar :section',
    'delete' => 'Eliminar',
    'remove' => 'Quitar',
    'add' => 'Añadir',
    'custom_color' => 'Color personalizado',
    'back' => 'Atrás',
    'close' => 'Cerrar',
    'dismiss' => 'Descartar',
    'search' => 'Buscar',
    'filter' => 'Filtrar',
    'clear' => 'Limpiar',
    'clear_all' => 'Limpiar todo',
    'apply' => 'Aplicar',
    'show_more' => 'Ver más',
    'show_less' => 'Ver menos',
    'view' => 'Ver',
    'yes' => 'Sí',
    'no' => 'No',
    'optional' => '(opcional)',
    'not_set' => 'Sin definir',
    'on' => 'Sí',
    'off' => 'No',
    'none' => 'Ninguno',
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'saving' => 'Guardando…',
    'loading' => 'Cargando…',

    /**
     * El selector de calendario compartido (x-date-field).
     */
    'choose_a_date' => 'Elige una fecha',
    'today' => 'Hoy',
    'month' => 'Mes',
    'year' => 'Año',
    'previous_month' => 'Mes anterior',
    'next_month' => 'Mes siguiente',
    'language' => 'Idioma',
    'coming_soon' => 'Próximamente',
    'setup_required' => 'Configuración pendiente',
    'view_only' => 'Solo lectura',

    'upload' => [
        'choose' => 'Elegir imagen',
        'progress' => 'Progreso de la subida',
        'cancel' => 'Cancelar la subida',
        'too_large' => 'Esa imagen supera los 2 MB.',
        'failed' => 'No se ha podido subir esa imagen. Inténtalo de nuevo.',
    ],
    /** Whether someone agreed to be contacted. Rendered by <x-consent-status>. */
    'consent' => [
        'opted_in' => 'Suscrito',
        'opted_out' => 'No suscrito',
    ],
    /** Asked before anything is taken off a record. */
    'confirm' => [
        'deactivate' => 'Desactivar',
        'activate' => 'Activar',
        'remove_tag_title' => '¿Quitar la etiqueta?',
        'remove_tag' => '¿Quitar “:label” de este cliente?',
        'remove_behavioral_title' => '¿Quitar la etiqueta de comportamiento?',
        'remove_behavioral' => '¿Quitar “:label” de este cliente?',
    ],

    /*
    | Los mensajes que el navegador escribe mientras se rellena un formulario.
    | Redactados igual que los del servidor, para que corregir un campo antes
    | de enviar y corregirlo después se lean igual. :field es la etiqueta.
    */
    'validation' => [
        'required' => ':field es obligatorio.',
        'email' => 'Introduce una dirección de correo válida.',
        'url' => 'Introduce una dirección web válida, empezando por https://',
        'phone' => 'Introduce un número de teléfono válido.',
        'date' => 'Introduce una fecha válida.',
        'numeric' => ':field debe ser un número.',
        'integer' => ':field debe ser un número entero.',
        'min' => ':field debe tener al menos :min caracteres.',
        'max' => ':field debe tener :max caracteres o menos.',
        'min_value' => ':field debe ser :min o más.',
        'max_value' => ':field debe ser :max o menos.',
        'taken' => 'Ese valor ya está en uso.',
    ],
    'type_a_time' => 'Escribe una hora, p. ej. 2:30 PM',

    /*
    | La estructura que dibuja cada plantilla: la barra de avisos sobre la
    | barra de la aplicación, el pie de página y el aviso que aparece cuando
    | los recursos de una página se han quedado obsoletos.
    */
    'stale_assets' => 'Esta página está desactualizada, así que algunas partes no funcionarán. ',
    'reload' => 'Recargar',
    'legal' => 'Legal',
    'terms' => 'Términos',
    'privacy' => 'Privacidad',
    'support' => 'Soporte',
    'all_rights_reserved' => '© :year StyleDesk. Todos los derechos reservados.',

    'banner' => [
        'watch_now' => 'Ver ahora: primeros pasos con StyleDesk',
        'trial_remaining' => '{0} Tu prueba gratuita termina hoy|{1} Queda :count día de prueba gratuita|[2,*] Quedan :count días de prueba gratuita',
        'trial_ending' => 'Tu prueba termina pronto',
        'subscribe' => 'Suscríbete ahora',
    ],

    'session' => [
        'expiring' => 'Tu sesión está a punto de caducar',
        'expiring_body' => 'Por tu seguridad, cerraremos tu sesión porque no ha habido actividad.',
        /* :time es la cuenta atrás; la vista la envuelve en su propio elemento. */
        'countdown' => 'La sesión caduca en :time',
        'sign_out' => 'Cerrar sesión',
        'stay' => 'Seguir con la sesión abierta',
        'expired' => 'Tu sesión ha caducado',
        'expired_body' => 'Tu sesión terminó porque no hubo actividad. Vuelve a iniciar sesión para continuar.',
        'sign_in' => 'Iniciar sesión',
    ],
];
