<?php

declare(strict_types=1);

/* Configuración → Clientes. */

return [
    'title' => 'Clientes',
    'intro' => 'Qué contiene una ficha de cliente, cómo se comporta y qué ve tu equipo al abrirla.',
    'saved' => 'Configuración de clientes actualizada correctamente.',
    'scope_note' => 'Estos son ajustes globales. Las fichas de clientes, su historial y la gestión diaria pertenecen al módulo de Clientes.',
    'pending_note' => 'Los ajustes que describen las pantallas de reserva y las fichas de cliente se guardan ahora y se aplicarán cuando esas pantallas existan.',

    'cards' => [
        'records' => 'Fichas de cliente',
        'records_hint' => 'Campos de la ficha, cuáles son obligatorios, cómo se escriben los nombres y el ID de cliente.',
        'lists' => 'Preferencias y etiquetas',
        'lists_hint' => 'Las preferencias estructuradas y las etiquetas que tu equipo puede poner a un cliente.',
        'notes' => 'Notas',
        'notes_hint' => 'Las notas de cliente, quién puede escribirlas y dónde aparecen.',
        'booking' => 'Comportamiento en la reserva',
        'booking_hint' => 'Qué muestra la pantalla de reserva sobre el cliente seleccionado.',
        'duplicates' => 'Detección de duplicados',
        'duplicates_hint' => 'Cómo se detecta un posible duplicado y qué ocurre entonces.',
        'communication' => 'Comunicación',
        'communication_hint' => 'Las formas de contactar con un cliente y a qué puede dar su consentimiento.',
        'status' => 'Estado y archivado',
        'status_hint' => 'Clientes activos, inactivos y archivados, y dónde aparece cada uno.',
        'privacy' => 'Privacidad y consentimiento',
        'privacy_hint' => 'Qué se registra cuando un cliente da o retira su consentimiento de marketing.',
    ],

    'defaults' => [
        'title' => 'Valores predeterminados de un cliente nuevo',
        'status' => 'Estado',
        'location' => 'Ubicación preferida',
        'staff' => 'Profesional preferido',
        'communication' => 'Método de contacto',
        'marketing' => 'Consentimiento de marketing',
        'none' => 'Ninguno',
    ],

    'fields' => [
        'title' => 'Campos de la ficha',
        'hint' => 'Qué campos tiene una ficha de cliente y cuáles hay que rellenar. Arrastra para reordenar.',
        'field' => 'Campo',
        'enabled' => 'Activo',
        'required' => 'Obligatorio',
        'locked' => 'Siempre activo',
        'locked_hint' => 'Una ficha de cliente sin nombre no le sirve a nadie.',
        'required_disabled' => 'Un campo desactivado no puede ser obligatorio.',
        'contact_advice' => 'Un móvil o un correo es lo que permite encontrar un duplicado. Merece la pena exigir al menos uno de los dos.',
    ],

    'name_format' => 'Cómo se escriben los nombres de los clientes',
    'name_format_hint' => 'Se usa en todos los sitios donde aparece un cliente, para que una misma persona no parezca dos en dos pantallas.',
    'name_preview' => 'Por ejemplo',

    'client_id' => 'ID de cliente',
    'client_id_hint' => 'Se asigna automáticamente, es único en tu negocio y se puede buscar. No se puede editar.',
    'client_id_example' => 'Por ejemplo :example',

    'preferences' => [
        'title' => 'Preferencias de cliente',
        'enabled' => 'Activar las preferencias de cliente',
        'multiple' => 'Permitir más de una preferencia por cliente',
        'add' => 'Añadir preferencia',
        'placeholder' => 'Cuero cabelludo sensible',
        'empty' => 'Todavía no hay preferencias.',
        'deactivate_note' => 'Al desactivarla se mantiene en los clientes que ya la tienen; solo deja de ofrecerse.',
    ],

    'tags' => [
        'title' => 'Etiquetas de cliente',
        'enabled' => 'Activar las etiquetas de cliente',
        'add' => 'Añadir etiqueta',
        'placeholder' => 'VIP',
        'colour' => 'Color',
        'empty' => 'Todavía no hay etiquetas.',
        'multiple_note' => 'Un cliente puede llevar varias etiquetas.',
    ],

    'notes' => [
        'enabled' => 'Activar las notas de cliente',
        'multiple' => 'Permitir más de una nota por cliente',
        'in_booking' => 'Mostrar las notas al reservar',
        'important_on_profile' => 'Mostrar las notas importantes al principio de la ficha',
        'allow_important' => 'Permitir al equipo marcar una nota como importante',
        'staff_can_edit' => 'Permitir al equipo editar sus propias notas',
        'admin_can_delete' => 'Permitir al propietario y a los administradores eliminar notas',
    ],

    'duplicates' => [
        'rules' => 'Considerar posible duplicado cuando coincida',
        'warning' => 'Avisar antes de crear un posible duplicado',
        'show_matches' => 'Mostrar los clientes coincidentes, para poder abrir el que ya existe',
        'no_merge' => 'Las fichas nunca se fusionan automáticamente. Dos personas pueden compartir un teléfono, y un historial mal fusionado no es algo que recepción pueda deshacer.',
    ],

    'search' => 'Buscar un cliente por',
    'creation' => 'Se pueden crear clientes desde',
    'creation_unavailable' => 'Todavía no disponible',
    'booking_panels' => 'Mostrar en la pantalla de reserva',
    'history_panels' => 'Mostrar en la ficha del cliente',

    'status' => [
        'allow_booking_inactive' => 'Permitir reservar a clientes inactivos',
        'archived_in_search' => 'Incluir clientes archivados en la búsqueda de reservas',
        'explainer' => 'Los clientes activos aparecen con normalidad. Los inactivos aparecen con una etiqueta. Los archivados quedan fuera de la búsqueda de reservas pero conservan sus citas, transacciones e historial.',
    ],

    'communication' => [
        'methods' => 'Formas de contactar con un cliente',
        'marketing' => 'Marketing al que puede dar su consentimiento',
        'stored_separately' => 'Cada uno se guarda por separado, así que un cliente que acepta recordatorios no ha aceptado marketing.',
    ],

    'consent' => [
        'record' => 'Registrar el consentimiento de marketing',
        'record_date' => 'Registrar cuándo se dio',
        'record_captured_by' => 'Registrar quién lo recogió',
        'client_can_opt_out' => 'Permitir que los clientes se den de baja',
        'show_on_profile' => 'Mostrar el estado del consentimiento en la ficha',
        'later' => 'Los formularios de consentimiento digitales, el consentimiento de tratamiento y de fotos, la aceptación de la política de privacidad y las firmas electrónicas llegarán en una versión posterior.',
    ],

    'deactivate' => 'Desactivar',
    'activate' => 'Activar',

    'preference_added' => 'Preferencia añadida.',
    'preference_activated' => 'Preferencia activada.',
    'preference_deactivated' => 'Preferencia desactivada. Los clientes que ya la tienen la conservan.',
    'tag_added' => 'Etiqueta añadida.',
    'tag_saved' => 'Etiqueta actualizada.',
    'tag_activated' => 'Etiqueta activada.',
    'tag_deactivated' => 'Etiqueta desactivada. Los clientes que ya la tienen la conservan.',
    'order_saved' => 'Orden guardado.',

    'validation' => [
        'status_required' => 'Elige un estado predeterminado.',
        'name_format_required' => 'Elige cómo se escriben los nombres de los clientes.',
        'location_invalid' => 'Elige una de tus propias ubicaciones.',
        'staff_invalid' => 'Elige a uno de tus propios miembros del equipo.',
        'preference_exists' => 'Ya tienes una preferencia con ese nombre.',
        'tag_exists' => 'Ya tienes una etiqueta con ese nombre.',
    ],
];
