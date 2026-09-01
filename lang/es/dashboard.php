<?php

declare(strict_types=1);

return [

    'title' => 'Panel',
    'greeting' => [
        'morning' => 'Buenos días',
        'afternoon' => 'Buenas tardes',
        'evening' => 'Buenas noches',
    ],

    'location' => 'Ubicación',
    'all_locations' => 'Todas las ubicaciones',

    'performance' => [
        'title' => 'Rendimiento del negocio',
        'today' => 'Cobrado hoy',
        'month' => 'Cobrado este mes',
        'change' => 'frente a los mismos días del mes pasado',
        'outstanding' => 'Pendiente',
        'bookings' => 'Reservas este mes',
        'average' => 'Reserva media',
        'no_comparison' => 'Aún no hay cifras del mes pasado',
    ],

    'bookings_today' => [
        'title' => 'Reservas de hoy',
        'total' => 'Total',
        'pending_checkin' => 'Pendientes de llegada',
        'checked_in' => 'Registrados',
        'completed' => 'Completadas',
        'cancelled' => 'Canceladas',
        'no_show' => 'No asistió',
    ],

    'checkin' => [
        'title' => 'Registro de llegada',
        'none' => 'Nadie está esperando para registrarse.',
        'none_hint' => 'Las citas aparecerán aquí a lo largo del día.',
        'view_all' => 'Abrir la lista',
        'action' => 'Registrar llegada',
    ],

    'arriving' => [
        'title' => 'Llegan pronto',
        'none' => 'No hay nadie previsto en la próxima hora.',
    ],

    'waiting' => [
        'title' => 'Esperando',
        'none' => 'No hay nadie esperando.',
        'for' => 'Esperando :count min',
        'since' => 'Llegada registrada a las :time',
        'too_long' => 'Lleva un buen rato',
    ],

    'staff_today' => [
        'title' => 'Trabajan hoy',
        'none' => 'Hoy no hay nadie en el cuadrante.',
        'shift' => 'Turno',
        'now' => 'Con un cliente',
        'next' => 'Siguiente',
        'remaining' => 'Quedan :count',
        'free' => 'Libre',
    ],

    'schedule_issues' => [
        'title' => 'Problemas de cuadrante',
        'none' => 'No hay incidencias que requieran tu atención.',
        'working_without_a_shift' => 'Trabajan sin turno en el cuadrante',
        'bookings_without_staff' => 'Citas sin nadie asignado',
    ],

    'clients' => [
        'title' => 'Clientes',
        'new_today' => 'Nuevos hoy',
        'booked_today' => 'Hoy en el salón',
        'returning_today' => 'Repiten',
        'active' => 'Clientes activos',
    ],

    'services' => [
        'title' => 'Lo más reservado este mes',
        'none' => 'Todavía no hay reservas este mes.',
        'bookings' => ':count reservas',
    ],

    'payments' => [
        'title' => 'Pagos',
        'collected' => 'Cobrado hoy',
        'outstanding' => 'Pendiente',
        'partial' => 'Pago parcial',
        'due_today' => 'Atendidos hoy y sin pagar',
        'none' => 'No queda nada pendiente de hoy.',
    ],

    'alerts' => [
        'title' => 'Requiere atención',
        'none' => 'No hay incidencias que requieran tu atención.',
        'late' => ':count cliente pendiente de llegar va con retraso|:count clientes pendientes de llegar van con retraso',
        'waiting_too_long' => ':count cliente lleva esperando un buen rato|:count clientes llevan esperando un buen rato',
        'unpaid' => ':count cita atendida hoy sigue sin pagar|:count citas atendidas hoy siguen sin pagar',
        'unstaffed' => ':count cita de hoy no tiene a nadie asignado|:count citas de hoy no tienen a nadie asignado',
    ],

    'my_next_client' => [
        'title' => 'Siguiente cliente',
        'none' => 'No tienes más citas programadas para hoy.',
        'none_hint' => 'Lo que se reserve más tarde aparecerá aquí.',
        'here' => 'Ya está aquí',
        'view_client' => 'Ver cliente',
        'view_booking' => 'Ver reserva',
    ],

    'my_day' => [
        'title' => 'Mi día',
        'none' => 'Hoy no tienes nada reservado.',
    ],

    'my_schedule' => [
        'title' => 'Mi turno',
        'none' => 'Hoy no estás en el cuadrante.',
        'from' => 'Desde',
        'to' => 'Hasta',
        'break' => 'Descanso',
        'remaining' => 'Aún por delante',
    ],

    'my_performance' => [
        'title' => 'Mi día hasta ahora',
        'clients' => 'Clientes',
        'completed' => 'Completadas',
        'average' => 'Servicio medio',
        'tips' => 'Propinas',
        'minutes' => ':count min',
    ],

    'quick_actions' => [
        'title' => 'Acciones rápidas',
        'booking' => 'Crear reserva',
        'client' => 'Añadir cliente',
        'checkin' => 'Registrar llegada',
        'staff' => 'Añadir personal',
        'service' => 'Añadir servicio',
        'schedule' => 'Gestionar cuadrante',
        'calendar' => 'Ver calendario',
    ],
];
