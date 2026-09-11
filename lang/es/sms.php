<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| StyleDesk SMS
|--------------------------------------------------------------------------
|
| What the desk reads about a text message: where it got to, and what it was
| about.
|
*/

return [
    'title' => 'SMS',

    'statuses' => [
        'queued' => ['label' => 'En cola'],
        'sending' => ['label' => 'Enviando'],
        'sent' => ['label' => 'Enviado'],
        'delivered' => ['label' => 'Entregado'],
        'failed' => ['label' => 'Fallido'],
        'rejected' => ['label' => 'Rechazado'],
        'expired' => ['label' => 'Caducado'],
        'opted_out' => ['label' => 'Baja'],
    ],

    'types' => [
        'reply' => 'Respuesta del cliente',
        'test' => 'Mensaje de prueba',
        'booking_confirmation' => 'Confirmación de reserva',
        'appointment_reminder' => 'Recordatorio de cita',
        'booking_rescheduled' => 'Reserva reprogramada',
        'booking_cancelled' => 'Reserva cancelada',
        'birthday' => 'Felicitación de cumpleaños',
        'membership' => 'Avisos de membresía',
    ],

    'registration' => [
        'not_started' => 'Sin iniciar',
        'submitted' => 'Enviado',
        'pending' => 'Pendiente',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'suspended' => 'Suspendido',
    ],

    'settings' => [
        'sending_from' => 'Enviando desde :number',
        'test' => 'Enviar un mensaje de prueba',
        'test_hint' => 'Envía un SMS por el mismo camino que una confirmación de reserva y lo registra igual que cualquier otro.',
        'test_to' => 'Enviar a',
        'send_test' => 'Enviar SMS',
        'test_body' => 'Mensaje de prueba de :business. StyleDesk SMS funciona.',
        'test_sent' => 'Mensaje de prueba enviado a :number mediante :provider.',
        'test_failed' => 'No se pudo enviar el mensaje de prueba. :reason',
        'test_unknown' => 'El proveedor no dio ningún motivo.',
        'test_bad_number' => 'Eso no parece un número de teléfono.',
        'test_live' => 'Telnyx está conectado, así que esto llegará a un teléfono real y se cobrará.',
        'test_local' => 'No hay operador conectado, así que no llegará a ningún teléfono: el mensaje se guarda en el registro.',
        'title' => 'Configuración de SMS',
        'intro' => 'Qué envía StyleDesk por SMS a tus clientes, desde qué número y cuánto estás dispuesto a gastar.',
        'sender' => 'Número SMS',
        'sender_hint' => 'StyleDesk gestiona el número y su registro con el operador en tu nombre. No se puede enviar nada a un móvil de EE. UU. hasta que el registro esté aprobado.',
        'no_number' => 'Sin asignar',
        'registration' => 'Registro',
        'saved' => 'Configuración de SMS guardada.',
        'enable' => 'Activar StyleDesk SMS',
        'enable_hint' => 'Los mensajes solo se envían mientras esto está activo.',
        'disabled_note' => 'Los SMS están desactivados. No se envía nada y no se pregunta nada más.',
        'messages' => 'Mensajes transaccionales',
        'messages_hint' => 'Qué mensajes salen. Cada uno se envía solo a clientes que lo han aceptado.',
        'not_yet' => 'Todavía no disponible.',
        'reminders' => 'Recordatorios de cita',
        'reminders_hint' => 'Con cuánta antelación se envía un recordatorio. Elige varios para enviar varios.',
        'hours_before' => '{1} 1 hora antes|[2,*] :count horas antes',
        'birthday_at' => 'Enviar felicitaciones a las',
        'birthday_at_hint' => 'En la hora local de la ubicación.',
        'spend' => 'Uso y límites',
        'spend_hint' => 'Un tope, para que una importación o un error no envíe SMS a toda tu lista.',
        'monthly_limit' => 'Límite mensual de mensajes',
        'no_limit' => 'Sin límite',
        'alert_at' => 'Avisarme al',
        'used_this_month' => 'Mensajes este mes',
        'segments_this_month' => 'Segmentos este mes',
    ],

    'errors' => [
        'disabled' => 'StyleDesk SMS está desactivado en esta instalación.',
        'no_sender' => 'No hay número de envío. Añade uno en Configuración → SMS, o define TELNYX_FROM_NUMBER.',
    ],

    'confirmation' => [
        'not_requested' => 'No solicitado',
        'pending' => 'Esperando al cliente',
        'confirmed' => 'Confirmado por el cliente',
        'cancellation_requested' => 'Cancelación solicitada',
        'needs_review' => 'Requiere revisión',
    ],
];
