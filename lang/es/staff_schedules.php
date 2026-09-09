<?php

declare(strict_types=1);

/*
| El cuadro de horarios del equipo: una fila por persona, una columna por mes.
|
| Las tres situaciones se nombran por separado a propósito, porque distinguirlas
| de un vistazo es justo para lo que existe esta pantalla: Publicado es un mes
| del que ya se ha avisado por correo, Borrador es uno planificado sin enviar, y
| Sin horario es un mes en el que nadie ha pensado todavía.
*/

return [

    'title' => 'Horario del personal',
    'intro' => 'Qué meses están cubiertos, cuáles siguen en borrador y quién no tiene nada planificado.',
    'summary' => '{0} Ningún miembro del personal|{1} :count miembro del personal|[2,*] :count miembros del personal',

    'assign' => 'Asignar horario',
    'add_shift' => 'Añadir turno',

    'search_label' => 'Buscar personal',
    'search_placeholder' => 'Busca por nombre, correo o puesto',

    'filters' => [
        'month' => 'Mes',
        'year' => 'Año',
        'status' => 'Estado del horario',
        'coverage' => 'Cobertura del horario',
        'all_statuses' => 'Todos los estados',
        'all_coverage' => 'Todos los meses',
        'apply' => 'Aplicar',
        'reset' => 'Restablecer',
    ],

    'statuses' => [
        'scheduled' => 'Con horario',
        'not-scheduled' => 'Sin horario',
        'draft' => 'Borrador',
        'published' => 'Publicado',
    ],

    'coverage' => [
        'completed' => 'Meses pasados',
        'current' => 'Mes actual',
        'future' => 'Meses futuros',
    ],

    'states' => [
        'published' => 'Publicado',
        'draft' => 'Borrador',
        'changes' => 'Cambios pendientes',
        'not-scheduled' => 'Sin horario',
    ],

    'columns' => [
        'staff' => 'Miembro del personal',
    ],

    'summary_line' => 'Mes :month · Año :year',
    'shifts_count' => '{1} :count turno|[2,*] :count turnos',
    'scroll_hint' => 'Desplázate a izquierda y derecha para ver todos los meses.',
    'this_month' => 'Este mes',
    'cell_hint' => ':name · :month',
    'open_schedule' => 'Abrir el horario de :name para :month',
    'start_schedule' => 'Asignar a :name un horario para :month',

    'menu' => [
        'view' => 'Ver el horario de :month',
        'assign' => 'Asignar el horario de :month',
    ],

    'actions_for' => 'Acciones para :name',
    'showing' => 'Mostrando :from–:to de :total del personal',
    'results' => [
        'zero' => 'Ningún miembro del personal',
        'one' => '1 miembro del personal',
        'many' => ':count miembros del personal',
        'clear' => 'Quitar filtros',
    ],
    'empty' => 'Ningún miembro del personal coincide con estos filtros.',
    'empty_hint' => 'Quita los filtros para ver a todo el equipo.',
    'no_months' => 'Ningún mes coincide con este filtro de cobertura.',

    'start' => [
        'title' => 'Asignar horario',
        'intro' => 'Elige para quién es el horario y qué mes cubre. El mes se planifica entero.',
        'staff' => 'Miembro del personal',
        'choose_staff' => 'Busca o elige un miembro del personal',
        'continue' => 'Continuar al horario',
    ],

    'month' => [
        'date' => 'Fecha',
        'day' => 'Día',
        'shift' => 'Turno',
        'start' => 'Inicio',
        'end' => 'Fin',
        'break' => 'Descanso',
        'hours' => 'Horas totales',
        'status' => 'Estado',
        'split' => 'Turno :index',
        'minutes' => ':count min',
        'edit' => 'Editar / Reasignar horario',
        'loading' => 'Cargando…',
        'failed' => 'No se ha podido cargar ese horario.',
    ],

    'legend' => [
        'title' => 'Leyenda',
        'published' => 'Ya se ha avisado por correo de este mes.',
        'draft' => 'Planificado, pero aún no se ha avisado a nadie.',
        'not_scheduled' => 'Nada planificado: requiere atención.',
    ],
];
