<?php

declare(strict_types=1);

/*
| La pantalla de turnos. Ver lang/en/shifts.php para el porqué de cada línea.
*/

return [

    'title' => 'Turnos',
    'intro' => 'Horas de trabajo en una fecha concreta. Un turno prevalece sobre el horario recurrente de ese día.',
    'add' => 'Añadir turno',
    'add_title' => 'Añadir un turno',
    'edit_title' => 'Editar turno',
    'adding' => 'Añadiendo…',
    'saving' => 'Guardando…',
    'save' => 'Guardar turno',
    'save_and_add_another' => 'Guardar y añadir otro',

    'created' => 'Turno añadido para :name.',
    'updated' => 'Turno actualizado.',
    'deleted' => 'Turno eliminado.',
    'delete_confirm' => '¿Eliminar este turno de :name el :date?',
    'correct_fields' => 'Revisa los campos marcados e inténtalo de nuevo.',

    'fields' => [
        'staff' => 'Miembro del personal',
        'staff_placeholder' => 'Busca o selecciona personal',
        'location' => 'Ubicación',
        'location_placeholder' => 'Busca o selecciona una ubicación',
        'date' => 'Fecha',
        'starts_at' => 'Hora de inicio',
        'ends_at' => 'Hora de fin',
        'break' => 'Descanso',
        'break_hint' => 'Tiempo no remunerado dentro del turno.',
        'type' => 'Tipo de turno',
        'status' => 'Estado',
        'notes' => 'Notas',
        'notes_placeholder' => 'Lo que deba saber quien lea el cuadrante.',
    ],

    'columns' => [
        'staff' => 'Personal',
        'date' => 'Fecha',
        'hours' => 'Horas',
        'break' => 'Descanso',
        'location' => 'Ubicación',
        'type' => 'Tipo',
        'status' => 'Estado',
    ],

    'filters' => [
        'all_staff' => 'Todo el personal',
        'all_locations' => 'Todas las ubicaciones',
        'all_types' => 'Todos los tipos',
        'all_statuses' => 'Todos los estados',
        'from' => 'Desde',
        'until' => 'Hasta',
    ],

    'types' => [
        'regular' => 'Regular',
        'overtime' => 'Horas extra',
        'cover' => 'Sustitución',
        'training' => 'Formación',
        'on-call' => 'De guardia',
        'custom' => 'Personalizado',
    ],

    'statuses' => [
        'scheduled' => 'Programado',
        'confirmed' => 'Confirmado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ],

    'minutes' => ':count min',
    'no_break' => 'Ninguno',

    'view' => 'Ver turno',
    'duplicate' => 'Duplicar',
    'cancel_shift' => 'Cancelar turno',
    'cancel_confirm' => '¿Cancelar este turno? Se queda en el cuadrante marcado como cancelado, para que se vea que se anuló.',
    'cancelled_toast' => 'Turno cancelado.',

    'validation' => [
        'business_closed' => 'El negocio cierra el :day, así que nadie puede trabajar ese día. Cambia el día, o ábrelo en Ajustes → Negocio → Horario de trabajo.',
        'outside_business_hours' => 'Ese horario queda fuera del horario de apertura del negocio (:hours).',
        'ends_after_start' => 'La hora de fin debe ser posterior a la de inicio.',
        'break_too_long' => 'El descanso es más largo que el turno.',
        'clash' => ':name ya tiene un turno que se solapa con esas horas ese día.',
        'staff_required' => 'Elige quién trabaja este turno.',
        'date_required' => 'Elige el día de este turno.',
    ],

    'results' => [
        'zero' => 'No se han encontrado turnos',
        'one' => '1 turno encontrado',
        'many' => ':count turnos encontrados',
        'empty' => 'Ningún turno coincide con tu búsqueda o filtros.',
        'clear' => 'Quitar filtros',
    ],
    'showing' => 'Mostrando :from–:to de :total turnos',
    'none_yet' => 'Todavía no hay turnos',
    'none_yet_hint' => 'Añade un turno cuando alguien trabaje horas que su horario recurrente no cubra: un sábado, una sustitución, una tarde de formación.',
];
