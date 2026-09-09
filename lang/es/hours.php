<?php

declare(strict_types=1);

/* El módulo de Horario comercial: el resumen y el editor por ubicación. */

return [
    'title' => 'Horario comercial',
    'intro' => 'El horario de apertura de cada ubicación, junto con los festivos, cierres y horarios especiales que lo modifican. Las horas se muestran en la zona horaria de cada ubicación.',
    'timezone_note' => 'Las horas están en la zona horaria de esta ubicación, :name (:identifier).',

    'edit_hours' => 'Editar horario',
    'save' => 'Guardar horario',
    'saved' => 'Horario comercial guardado correctamente.',
    'saved_from' => 'Horario guardado, a partir del :date.',
    'also_applied' => '{1} También aplicado a :count ubicación más.|[2,*] También aplicado a :count ubicaciones más.',
    'schedule_discarded' => 'Horario futuro descartado.',
    'correct_fields' => 'Corrige los campos marcados e inténtalo de nuevo.',

    'no_locations' => 'Todavía no hay ubicaciones.',
    'no_locations_hint' => 'El horario pertenece a una ubicación, así que añade una primero.',
    'add_location' => 'Añadir una ubicación',

    'upcoming' => [
        'title' => 'Próximamente',
        'hint' => 'Festivos, cierres y horarios especiales de los próximos 12 meses.',
        'in_progress' => 'En curso',
        'summary' => '{1} :count excepción próxima|[2,*] :count excepciones próximas',
        'and_more' => 'y :count más',
    ],

    'future' => [
        'starts' => 'El nuevo horario empieza el :date.',
        'review' => 'Revísalo',
        'editing' => 'Estás editando el horario que empieza el :date. El horario de hoy no cambia.',
        'edit_today' => 'Editar el horario de hoy',
        'pending' => 'Un horario distinto empieza el :date. Los cambios de aquí se aplican hasta entonces.',
        'edit_those' => 'Editar ese horario',
        'discard' => 'Descartar',
        'discard_confirm' => '¿Descartar este horario futuro? Se mantendrá el horario actual.',
    ],

    'effective' => [
        'title' => 'Cuándo empieza este horario',
        'hint' => 'Déjalo en blanco para cambiar el horario vigente ahora. Elige una fecha para planificar un cambio con antelación: el horario actual se mantiene hasta entonces.',
        'label' => 'A partir del',
    ],

    'apply' => [
        'title' => 'Aplicar a otras ubicaciones',
        'hint' => 'Copia esta semana a las sucursales que marques. Cada una conserva su propia copia, así que después puedes cambiar una sin cambiar las demás.',
        'warning' => 'Esto sustituye el horario de las ubicaciones marcadas para el mismo periodo.',
    ],

    'exceptions' => [
        'title' => 'Festivos, cierres y horarios especiales',
        'hint' => 'Fechas que modifican el horario semanal de arriba.',
        'add' => 'Añadir fecha',
        'empty' => 'No hay nada programado. Añade un festivo, un cierre o un día con horario distinto.',
        'add_title' => 'Añadir una fecha',
        'edit_title' => 'Editar esta fecha',
        'save' => 'Guardar fecha',
        'added' => 'Añadido al calendario.',
        'updated' => 'Entrada del calendario actualizada.',
        'removed' => 'Eliminado del calendario.',
        'delete_confirm' => '¿Quitar «:name» del calendario?',

        'type' => 'De qué se trata',
        'name' => 'Nombre',
        'name_placeholder' => 'Navidad',
        'from' => 'Desde',
        'to' => 'Hasta',
        'to_hint' => 'Déjalo en blanco si es un solo día.',
        'closed_all_day' => 'Cerrado todo el día',
        'closed_all_day_hint' => 'Desactívalo para abrir con un horario distinto.',
        'opens' => 'Abre',
        'closes' => 'Cierra',
        'notes' => 'Nota interna',
        'notes_placeholder' => 'Solo la ve tu equipo.',
    ],

    'validation' => [
        'name_required' => 'Ponle un nombre, para que el equipo sepa de qué se trata.',
        'date_required' => 'Elige una fecha.',
        'end_before_start' => 'La fecha de fin no puede ser anterior a la de inicio.',
        'opens_required' => 'Indica la hora de apertura o marca el día como cerrado.',
        'closes_required' => 'Indica la hora de cierre o marca el día como cerrado.',
        'closes_after_opens' => 'La hora de cierre debe ser posterior a la de apertura.',
        'effective_after' => 'Un horario futuro debe empezar en una fecha posterior. Déjalo en blanco para cambiar el horario de hoy.',
        'schedule_not_future' => 'Solo se puede descartar un horario que aún no haya empezado.',
        'clash' => '«:name» ya cubre :dates. Edita esa entrada o elige otras fechas.',
    ],

    'types' => [
        'public_holiday' => 'Festivo',
        'closure' => 'Cierre de la ubicación',
        'special_hours' => 'Horario especial',
        'training' => 'Día de formación',
        'maintenance' => 'Cierre por mantenimiento',
        'private_event' => 'Evento privado',
        'emergency' => 'Cierre de emergencia',
    ],
];
