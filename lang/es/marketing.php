<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
|
| Campañas de correo: lo que el negocio escribe a toda su lista de clientes,
| a diferencia del módulo de correo, que es una conversación con un solo
| cliente.
|
*/

return [

    'title' => 'Marketing por correo',
    'subtitle' => 'Campañas a tu lista de clientes',
    'intro' => 'Escribe a tus clientes como grupo —ofertas, novedades y recordatorios— y comprueba qué hicieron con el mensaje.',

    'create' => 'Crear campaña de correo',
    'edit' => 'Editar campaña',
    'saved' => 'Campaña guardada',
    'deleted' => 'Campaña eliminada',
    'delete' => 'Eliminar borrador',
    'delete_confirm' => '¿Eliminar este borrador? No se ha enviado a nadie, y esta acción no se puede deshacer.',
    'save_draft' => 'Guardar borrador',
    'cancel' => 'Cancelar',
    'search' => 'Buscar campañas…',
    'all_statuses' => 'Todos los estados',
    'filters_active' => 'Filtros',
    'clear' => 'Borrar',

    'summary' => [
        'campaigns' => 'Campañas',
        'sent' => 'Correos enviados',
        'delivery_rate' => 'Tasa de entrega',
        'open_rate' => 'Tasa de apertura',
        'click_rate' => 'Tasa de clics',
        'unsubscribed' => 'Bajas',
    ],

    'statuses' => [
        'draft' => 'Borrador',
        'scheduled' => 'Programada',
        'sending' => 'Enviando',
        'sent' => 'Enviada',
        'paused' => 'Pausada',
        'cancelled' => 'Cancelada',
        'failed' => 'Fallida',
    ],

    'table' => [
        'name' => 'Campaña',
        'audience' => 'Audiencia',
        'recipients' => 'Destinatarios',
        'scheduled' => 'Programada',
        'sent' => 'Enviada',
        'delivered' => 'Entregados',
        'opened' => 'Abiertos',
        'clicked' => 'Con clic',
        'author' => 'Creada por',
        'status' => 'Estado',
    ],

    /* Paso uno: qué es el correo y de quién viene. */
    'details' => [
        'title' => 'Datos de la campaña',
        'intro' => 'Cómo se llama esta campaña y qué verán tus clientes en su bandeja de entrada.',
        'name' => 'Nombre de la campaña',
        'name_hint' => 'Para tu propia referencia. Tus clientes nunca lo ven.',
        'name_placeholder' => 'Promoción de masajes de septiembre',
        'subject' => 'Asunto del correo',
        'subject_placeholder' => 'Ahorra un 20 % en tu próximo masaje',
        'preview_text' => 'Texto de vista previa',
        'preview_hint' => 'La línea que aparece tras el asunto en la mayoría de bandejas de entrada.',
        'preview_placeholder' => 'Reserva hoy tu cita de septiembre.',
        'from_name' => 'Nombre del remitente',
        'reply_to' => 'Correo de respuesta',
        'reply_hint' => 'A dónde van las respuestas. Déjalo en blanco para usar el correo del negocio.',
    ],

    /* Paso dos: a quién se envía. */
    'audience' => [
        'title' => 'Audiencia',
        'intro' => 'A quién se envía esta campaña. Las reglas se aplican en el momento del envío, así que una lista que crezca antes crecerá con ella.',
        'scope' => 'Clientes',
        'locations' => 'Ubicaciones',
        'tags' => 'Etiquetas',
        'staff' => 'Atendidos por',
        'services' => 'Que recibieron el servicio',
        'lapsed' => 'Sin visitar desde hace',
        'visited' => 'Visitó en los últimos',
        'booking' => 'Cita próxima',
        'days' => ':days días',
        'any' => 'Cualquiera',
        'has_upcoming' => 'Tiene una reservada',
        'no_upcoming' => 'No tiene nada reservado',

        'scopes' => [
            'all' => 'Todos los clientes',
            'active' => 'Clientes activos',
        ],

        /*
        | La columna de audiencia del listado, en palabras.
        |
        | Van en su propio grupo, porque si no cuatro de estas chocarían con
        | las etiquetas del formulario de arriba: «Sin visitar desde hace» es
        | la etiqueta de un campo y «sin visitas en 90 días» es una frase
        | sobre una campaña guardada, y la segunda sustituía en silencio a la
        | primera cuando compartían clave.
        */
        'said' => [
            'locations' => '{1} 1 ubicación|[2,*] :count ubicaciones',
            'tags' => '{1} 1 etiqueta|[2,*] :count etiquetas',
            'staff' => '{1} 1 miembro del personal|[2,*] :count miembros del personal',
            'services' => '{1} 1 servicio|[2,*] :count servicios',
            'lapsed' => 'sin visitas en :days días',
            'visited' => 'visitó en :days días',
            'has_upcoming' => 'tiene una cita próxima',
            'no_upcoming' => 'sin nada reservado',
        ],
    ],

    /*
    | La estimación. Tres números en lugar de uno, porque «1.248 clientes»
    | oculta los dos datos que un propietario necesita antes de pulsar enviar.
    */
    'estimate' => [
        'title' => 'Destinatarios estimados',
        'eligible' => 'Elegibles',
        'unsubscribed' => 'Dados de baja',
        'invalid' => 'Correo no válido',
        'total' => 'Coinciden con tus reglas',
        'counting' => 'Contando…',
        'hint' => 'Contado ahora. Las reglas se aplican de nuevo al enviar la campaña, así que esta cifra puede variar.',
        'none' => 'Todavía no hay nadie que cumpla estas reglas.',
        'all_unsubscribed' => 'Todos los que cumplen estas reglas se han dado de baja del correo de marketing.',
    ],

    /* Lo que está construido y lo que no. */
    'next' => [
        'title' => 'Pendiente',
        'intro' => 'Esta campaña se puede describir y guardar. Diseñarla y enviarla son los siguientes pasos.',
        'design' => 'Diseñar el correo',
        'preview' => 'Vista previa y prueba',
        'send' => 'Enviar o programar',
        'soon' => 'Próximamente',
    ],

    'empty' => 'Todavía no hay campañas.',
    'empty_hint' => 'Crea una para escribir a tus clientes como grupo.',
    'no_matches' => 'Ninguna campaña coincide con esa búsqueda.',

    'results' => [
        'zero' => 'No se han encontrado campañas',
        'one' => 'Se ha encontrado 1 campaña',
        'many' => 'Se han encontrado :count campañas',
    ],
    'showing' => 'Mostrando :from–:to de :total campañas',
    'actions_for' => 'Acciones para :name',

];
