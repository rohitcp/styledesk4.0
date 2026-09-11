<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
|
| The diary drawn against the clock: who is working, who is booked, and where
| the gaps are.
|
*/

return [
    'title' => 'Calendario',
    'subtitle' => 'Quién trabaja, quién tiene cita y dónde quedan huecos.',

    'nav' => [
        'previous' => 'Día anterior',
        'next' => 'Día siguiente',
        'today' => 'Hoy',
        'pick' => 'Elegir una fecha',
    ],

    'views' => [
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
    ],

    'filters' => [
        'location' => 'Ubicación',
        'staff' => 'Personal',
        'resource' => 'Recurso',
        'all_staff' => 'Todo el personal',
        'all_resources' => 'Todos los recursos',
        'all_locations' => 'Todas las ubicaciones',
        'interval' => 'Intervalo',
        'minutes' => ':count min',
        'service' => 'Servicio',
        'all_services' => 'Todos los servicios',
        'search' => 'Buscar…',
    ],

    'summary' => [
        'total' => 'Reservas',
        'arrived' => 'Registrados',
        'completed' => 'Completadas',
        'cancelled' => 'Canceladas',
        'no_show' => 'No asistió',
        'owing' => 'Saldo pendiente',
        'revenue' => 'Ingresos previstos',
    ],

    'card' => [
        'balance' => 'Saldo pendiente · :amount',
        'membership' => 'Membresía',
        /* Reserved until the client walks in — the rule the redemption engine
           keeps. A calendar that called a future appointment "used" would be
           telling the client they had already had it. */
        'credits_reserved' => 'Membresía · :count crédito reservado|Membresía · :count créditos reservados',
        'credits_used' => 'Membresía · :count crédito usado|Membresía · :count créditos usados',
        'note' => 'Tiene una nota',
    ],

    'more' => '+:count más',
    'preview' => [
        'status' => 'Estado',
        'payment' => 'Pago',
    ],

    'now' => 'Ahora · :time',
    'break' => 'Descanso de :minutes min',
    'off' => 'No trabaja',
    'closed' => 'Cerrado este día.',
    'no_staff' => 'Todavía no hay nadie configurado para prestar servicios en esta ubicación.',
    'empty' => 'No hay reservas este día.',
    'loading' => 'Cargando el día…',
    'new_booking' => '+ Nueva reserva',
    'free_slot' => 'Reservar con :staff a las :time',
];
