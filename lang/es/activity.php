<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Actividad del negocio
|--------------------------------------------------------------------------
*/

return [

    'title' => 'Actividad del negocio',
    'intro' => 'Lo que ha ido ocurriendo en el negocio.',
    'open' => 'Actividad',
    'close' => 'Cerrar actividad',
    'mark_read' => 'Marcar todo como leído',
    'unread' => ':count nuevas',
    'unread_capped' => '99+ nuevas',
    'loading' => 'Cargando…',
    'more' => 'Ver más',
    'empty' => 'Todavía no ha ocurrido nada.',
    'empty_hint' => 'Las reservas, los pagos y los cambios del negocio aparecen aquí según ocurren.',
    'empty_filtered' => 'Todavía nada de ese tipo.',

    'today' => 'Hoy',
    'yesterday' => 'Ayer',
    'earlier' => 'Anterior',

    'by' => 'Por :name',
    'system' => 'StyleDesk',

    'kinds' => [
        'booking' => 'Reserva',
        'reschedule' => 'Cambio',
        'cancel' => 'Cancelación',
        'checkin' => 'Entrada',
        'checkout' => 'Salida',
        'noshow' => 'Ausencia',
        'client' => 'Cliente',
        'note' => 'Nota',
        'file' => 'Archivo',
        'payment' => 'Pago',
        'deposit' => 'Depósito',
        'refund' => 'Reembolso',
        'staff' => 'Personal',
        'schedule' => 'Horario',
        'service' => 'Servicio',
        'resource' => 'Recurso',
        'email' => 'Correo',
        'sms' => 'SMS',
        'review' => 'Reseña',
        'coupon' => 'Cupón',
        'giftcard' => 'Tarjeta regalo',
        'login' => 'Acceso',
        'settings' => 'Ajustes',
    ],

    'groups' => [
        'all' => 'Todo',
        'bookings' => 'Reservas',
        'clients' => 'Clientes',
        'payments' => 'Pagos',
        'staff' => 'Personal',
        'scheduling' => 'Horarios',
        'communication' => 'Comunicación',
        'system' => 'Sistema',
    ],

    'links' => [
        'booking' => 'Ver reserva',
        'client' => 'Ver cliente',
        'staff' => 'Ver personal',
        'schedule' => 'Ver horario',
        'service' => 'Ver servicio',
        'resource' => 'Ver recurso',
        'payment' => 'Ver pago',
    ],

    'sentences' => [
        'status' => 'La reserva :reference se marcó como :status.',
        'reason' => 'Motivo: :reason.',
        'schedule' => 'Horario publicado para :name — :count turnos.',
    ],

    'audit' => [
        'fallback' => ':action — :name',
        'staff.created' => ':name se añadió al equipo.',
        'staff.edited' => 'Se actualizaron los datos de :name.',
        'staff.role_changed' => 'Se cambió el rol de :name.',
        'staff.deleted' => ':name se eliminó del equipo.',
        'staff.invitation_sent' => 'Se invitó a :name a unirse.',
        'auth.login' => ':name inició sesión.',
        'auth.logout' => ':name cerró sesión.',
        'auth.password_reset' => 'Se restableció la contraseña de :name.',
    ],

];
