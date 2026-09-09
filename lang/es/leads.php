<?php

declare(strict_types=1);

/*
| Reservas que se empezaron y no se terminaron.
*/

return [

    'title' => 'Reservas iniciadas',
    'intro' => 'Reservas que se empezaron pero nunca se terminaron. Cada una es una llamada que merece la pena devolver.',

    'search' => 'Busca por nombre, referencia o teléfono…',
    'search_label' => 'Buscar reservas iniciadas',
    'all_statuses' => 'Todos los estados',

    'actions' => [
        'complete' => 'Continuar reserva',
        'view_booking' => 'Ver reserva',
        'view_client' => 'Ver cliente',
    ],

    'columns' => [
        'who' => 'Cliente',
        'services' => 'Pidió',
        'expected' => 'Para el',
        'location' => 'Ubicación',
        'total' => 'Valor',
        'started' => 'Empezada',
        'step' => 'Se quedó en',
        'status' => 'Estado',
    ],

    'statuses' => [
        'draft' => ['label' => 'Borrador'],
        'new' => ['label' => 'Nueva'],
        'in-progress' => ['label' => 'En curso'],
        'awaiting-confirmation' => ['label' => 'Esperando al cliente'],
        'awaiting-deposit' => ['label' => 'Esperando depósito'],
        'payment-pending' => ['label' => 'Pago pendiente'],
        'follow-up' => ['label' => 'Hay que llamar'],
        'contacted' => ['label' => 'Contactada'],
        'converted' => ['label' => 'Convertida'],
        'abandoned' => ['label' => 'Abandonada'],
        'cancelled' => ['label' => 'Cancelada'],
        'lost' => ['label' => 'Perdida'],
        'expired' => ['label' => 'Caducada'],
    ],

    'steps' => [
        'service' => 'Servicio',
        'when' => 'Con quién y cuándo',
        'details' => 'Detalles de la reserva',
        'payment' => 'Depósito y pago',
        'comms' => 'Comunicación',
        'completed' => 'Terminada',
    ],

    'reasons' => [
        'changed-mind' => 'El cliente cambió de idea',
        'no-suitable-time' => 'Sin cita que le sirva',
        'staff-unavailable' => 'Su profesional no estaba libre',
        'price' => 'Precio',
        'duplicate' => 'Solicitud duplicada',
        'declined' => 'El cliente dijo que no',
        'unreachable' => 'No se le pudo localizar',
        'booked-elsewhere' => 'Reservó en otro sitio',
        'no-availability' => 'Sin disponibilidad',
        'other' => 'Otro',
    ],

    'contact_methods' => [
        'phone' => 'Teléfono',
        'sms' => 'SMS',
        'email' => 'Correo',
        'whatsapp' => 'WhatsApp',
        'in-person' => 'En persona',
    ],

    'drawer' => [
        'not_selected' => 'Sin elegir',
        'summary' => 'Reserva iniciada',
        'created' => 'Creada',
        'created_by' => 'Creada por',
        'last_activity' => 'Última actividad',
        'taken_by' => 'Último contacto por',
        'client' => 'Cliente',
        'name' => 'Nombre',
        'phone' => 'Teléfono',
        'email' => 'Correo',
        'preferences' => 'Preferencias de reserva',
        'booking' => 'Detalles de la reserva',
        'services' => 'Servicios',
        'duration' => 'Duración',
        'price' => 'Precio',
        'date' => 'Fecha',
        'time' => 'Hora',
        'staff' => 'Con',
        'location' => 'Ubicación',
        'notes' => 'Notas',
        'payment' => 'Pago',
        'estimated_total' => 'Total estimado',
        'deposit_required' => 'Depósito necesario',
        'deposit_paid' => 'Depósito pagado',
        'outstanding' => 'Pendiente',
        'payment_status' => 'Estado del pago',
        'journey' => 'Progreso de la reserva',
        'activity' => 'Actividad',
        'no_activity' => 'Aún no hay nada registrado.',
        'stopped_at' => 'Se quedó en',
        'view_client' => 'Abrir la ficha del cliente',
        'close' => 'Cerrar',
        'send_email' => 'Enviar correo',
        'send_sms' => 'Enviar SMS',
        'soon' => 'Pronto',
    ],

    'events' => [
        'created' => 'Reserva iniciada creada',
        'step' => ':step completado',
        'cancelled' => 'Cancelada: :reason',
        'converted' => 'Convertida en reserva',
        'contacted' => 'Cliente contactado',
    ],

    'notes' => [
        'button' => 'Notas',
        'title' => 'Notas',
        'write' => 'Añadir una nota',
        'placeholder' => 'Llamada a las 16:00, sin respuesta; lo intento mañana.',
        'hint' => 'Se guarda en la ficha del cliente y se etiqueta con esta reserva iniciada.',
        'save' => 'Guardar nota',
        'saved' => 'Nota guardada en la ficha del cliente.',
        'empty' => 'Aún no hay notas sobre esta reserva iniciada.',
        'needs_client' => 'Un cliente sin cita no tiene ficha donde guardar una nota.',
    ],

    'cancel' => [
        'title' => '¿Cancelar esta reserva iniciada?',
        'body' => 'Se marca como cancelada y se guarda en el historial, con quién la canceló y cuándo.',
        'reason' => 'Motivo',
        'choose_reason' => 'Elige un motivo',
        'note' => 'Nota',
        'note_placeholder' => 'Lo que deba saber la siguiente persona: opcional.',
        'keep' => 'Mantener',
        'confirm' => 'Cancelar la reserva iniciada',
        'cancelled' => 'Reserva iniciada cancelada.',
        'failed' => 'No se pudo guardar. Inténtalo de nuevo.',
    ],

    'empty' => 'Ninguna reserva iniciada coincide con estos filtros.',
    'none_yet' => 'Aún no hay reservas iniciadas',
    'none_yet_hint' => 'Se guarda una cuando alguien empieza una reserva y no la termina, así tienes la referencia y los servicios para devolver la llamada.',

    'showing' => 'Mostrando :from–:to de :total reservas iniciadas',
    'results' => [
        'zero' => 'Ninguna reserva iniciada',
        'one' => '1 reserva iniciada',
        'many' => ':count reservas iniciadas',
    ],
];
