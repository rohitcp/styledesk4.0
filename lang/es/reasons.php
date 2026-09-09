<?php

declare(strict_types=1);

return [

    'title' => 'Motivos',
    'intro' => 'Por qué pasan las cosas, como lista en vez de texto libre — para que "por qué perdemos reservas" sea una pregunta que los informes puedan responder.',
    'back' => 'Todas las listas de motivos',

    'types' => [
        'booking-cancellation' => ['label' => 'Cancelación de reserva', 'intro' => 'Por qué se anuló una cita.'],
        'booking-reschedule' => ['label' => 'Reprogramación de reserva', 'intro' => 'Por qué se movió una cita.'],
        'no-show' => ['label' => 'Ausencia', 'intro' => 'Por qué alguien no se presentó.'],
        'refund' => ['label' => 'Reembolso', 'intro' => 'Por qué se devolvió dinero.'],
        'payment-adjustment' => ['label' => 'Ajuste de pago', 'intro' => 'Por qué se cambió una factura ya emitida.'],
        'client-status-change' => ['label' => 'Cambio de estado del cliente', 'intro' => 'Por qué cambió el estado de un cliente.'],
        'staff-schedule-change' => ['label' => 'Cambio de horario del personal', 'intro' => 'Por qué cambió un turno, un horario o una ausencia.'],
        'booking-declined' => ['label' => 'Reserva rechazada', 'intro' => 'Por qué se rechazó una solicitud de cita.'],
        'service-cancellation' => ['label' => 'Baja de servicio', 'intro' => 'Por qué se dejó de ofrecer un servicio.'],
    ],

    'columns' => [
        'reason' => 'Motivo',
        'source' => 'Origen',
        'details' => 'Pide explicación',
        'status' => 'Estado',
        'action' => 'Acción',
    ],

    'system' => 'StyleDesk',
    'custom' => 'Tuyo',
    'renamed' => 'Renombrado',
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'activate' => 'Activar',
    'deactivate' => 'Desactivar',
    'edit' => 'Editar',
    'delete' => 'Eliminar motivo',
    'delete_confirm' => '¿Eliminar «:name»? Lo ya registrado con este motivo lo conserva; no se podrá usar de nuevo.',
    'system_undeletable' => 'Este lo aporta StyleDesk. Se puede renombrar o desactivar, pero no eliminar — lo registrado con él sigue teniendo que decir por qué.',

    'add' => 'Añadir motivo',
    'add_title' => 'Añadir un motivo',
    'edit_title' => 'Editar motivo',
    'name' => 'Motivo',
    'name_placeholder' => 'El cliente cambió de opinión',
    'description' => 'Descripción',
    'description_hint' => 'Opcional. Qué significa, para quien lo elija después.',
    'requires_details' => 'Pedir una explicación al elegirlo',
    'requires_details_hint' => 'Para los motivos que no son una respuesta por sí solos — «Otro» es el caso claro.',
    'details_label' => 'Detalles adicionales',

    'extra_title' => 'También se pregunta',
    'extra_hint' => 'Esta lista hace una segunda pregunta junto al motivo. Forma parte de cómo se registra :label y no es configurable.',

    'counts' => ':active de :total activos',
    'reorder_hint' => 'Arrastra para reordenar',
    'save_order' => 'Guardar orden',
    'order_changed' => 'El orden ha cambiado pero aún no se ha guardado.',

    'added' => 'Motivo añadido.',
    'updated' => 'Motivo actualizado.',
    'activated' => 'Motivo activado.',
    'deactivated' => 'Motivo desactivado. Lo ya registrado con él no cambia.',
    'deleted' => 'Motivo eliminado.',
    'order_saved' => 'Orden guardado.',
    'none' => 'Aún no hay motivos en esta lista.',
];
