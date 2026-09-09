<?php

declare(strict_types=1);

return [

    'title' => 'Ventas',
    'intro' => 'Consulta ingresos, pagos, saldos pendientes, reembolsos, propinas y la actividad de transacciones.',

    'period' => 'Intervalo de fechas',
    'periods' => [
        'today' => 'Hoy',
        'tomorrow' => 'Mañana',
        'yesterday' => 'Ayer',
        'last_3' => 'Últimos 3 días',
        'last_7' => 'Últimos 7 días',
        'week' => 'Esta semana',
        'month' => 'Este mes',
        'custom' => 'Intervalo personalizado',
    ],
    'from' => 'Desde',
    'to' => 'Hasta',
    'apply' => 'Aplicar',

    'widgets' => [
        'total_sales' => 'Ventas totales',
        'collected' => 'Pagos cobrados',
        'outstanding' => 'Saldo pendiente',
        'refunds' => 'Reembolsos',
        'tips' => 'Propinas cobradas',
        'transactions' => 'Transacciones',
    ],

    'notes' => [
        'total_sales' => 'Por fecha de la cita',
        'collected' => 'Por fecha del pago',
        'outstanding' => 'Aún pendiente',
        'refunds' => 'Por fecha del pago',
        'tips' => 'Por fecha del pago',
        'transactions' => 'Pagos cobrados',
    ],

    'vs_previous' => 'frente al periodo anterior',
    'show_these' => 'Ver estas →',

    /*
    | Las dos mitades se cuentan de forma distinta y la página lo dice. Un
    | depósito cobrado en agosto para una cita de septiembre es una venta de
    | septiembre y un pago de agosto; quien no lo sepa verá que los totales no
    | cuadran y concluirá que las cifras están mal.
    */
    'counting_note' => 'Las ventas y los saldos pendientes se cuentan por la fecha de la cita. Los pagos, los reembolsos y las propinas se cuentan por la fecha en que se movió el dinero.',

    'transactions' => 'Transacciones',
    'search_placeholder' => 'Transacción, reserva, cliente, correo o teléfono',
    'actions_for' => 'Acciones para :name',
    'showing' => 'Mostrando',
    'results' => [
        'zero' => 'Sin transacciones',
        'one' => '1 transacción',
        'many' => ':count transacciones',
        'clear' => 'Borrar filtros',
    ],
    'empty' => 'No se ha movido dinero en este periodo.',
    'walk_in' => 'Sin cita',

    'columns' => [
        'reference' => 'Transacción',
        'at' => 'Fecha y hora',
        'booking' => 'Reserva',
        'client' => 'Cliente',
        'services' => 'Servicio',
        'staff' => 'Personal',
        'location' => 'Ubicación',
        'total' => 'Total de la reserva',
        /* Este pago, frente a lo que la reserva ha cobrado en total: dos
           preguntas distintas, y la tabla responde a las dos. */
        'amount' => 'Pagado',
        'balance' => 'Saldo',
        'method' => 'Método',
        'status' => 'Estado',
    ],

    'drawer' => [
        'at_a_glance' => 'Resumen',
        'nothing_owed' => 'Nada pendiente',
        'next_appointment' => 'Próxima cita',
        'total_visits' => 'Visitas totales',
        'lifetime_spend' => 'Gasto acumulado',
        'last_visit' => 'Última visita',
        'tags' => 'Etiquetas',
        'account' => 'Cuenta',
        'bookings' => 'Reservas',
        'spend' => 'Total pagado',
        'owed' => 'Aún pendiente',
        'transaction' => 'Transacción',
        'booking' => 'Reserva',
        'amount' => 'Este pago',
        'tip' => 'Propina',
        'paid_total' => 'Pagado en esta reserva',
        'recorded_by' => 'Cobrado por',
        'online' => 'En línea',
        'processor_reference' => 'Referencia del procesador',
        'view_client' => 'Ver todos los datos',
        'view_receipt' => 'Ver recibo completo',
        'download' => 'Descargar PDF',
    ],

    'actions' => [
        'view_booking' => 'Ver reserva',
        'open_booking' => 'Abrir página de la reserva',
        'view_client' => 'Ver cliente',
        'view_receipt' => 'Ver recibo',
    ],
];
