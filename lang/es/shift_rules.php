<?php

declare(strict_types=1);

/*
| App Settings → Personal → Reglas de turno. Ver lang/en/shift_rules.php
| para el porqué de cada línea.
*/

return [

    'title' => 'Reglas de turno',
    'intro' => 'Patrones de trabajo reutilizables. Una regla es una plantilla: asignarla a alguien, y cambiar un día concreto, se hace desde Personal → Horario del personal.',
    'add' => 'Añadir regla de turno',
    'add_title' => 'Añadir una regla de turno',
    'edit_title' => 'Editar regla de turno',
    'saving' => 'Guardando…',
    'save' => 'Guardar regla',
    'save_and_add_another' => 'Guardar y añadir otra',

    'created' => ':name guardada.',
    'updated' => ':name actualizada.',
    'deleted' => 'Regla de turno eliminada.',
    'duplicated' => 'Copiada :name. Dale un nombre propio a la copia y guárdala.',
    'copy_of' => ':name (copia)',
    'made_active' => ':name está activa.',
    'made_inactive' => 'Ya no se ofrece :name para nuevos horarios.',

    'sections' => [
        'basics' => 'Información básica',
        'days' => 'Días y horas de trabajo',
        'break' => 'Descanso',
        'limits' => 'Reglas de horas',
        'flexibility' => 'Flexibilidad',
        'dates' => 'Fechas de vigencia',
        'split' => 'Ajustes de turno partido',
    ],

    'fields' => [
        'name' => 'Nombre de la regla',
        'name_placeholder' => 'Jornada completa estándar',
        'description' => 'Descripción',
        'description_placeholder' => 'Cuándo o por qué usar esta regla.',
        'location_scope' => 'Se aplica a',
        'locations' => 'Ubicaciones',
        'locations_placeholder' => 'Busca o selecciona ubicaciones',
        'status' => 'Estado',

        'break_type' => 'Descanso',
        'break_minutes' => 'Duración del descanso',
        'break_starts_at' => 'El descanso empieza',
        'break_ends_at' => 'El descanso termina',

        'max_hours_per_day' => 'Horas máximas por día',
        'max_hours_per_week' => 'Horas máximas por semana',
        'min_hours_per_shift' => 'Horas mínimas por turno',
        'max_hours_per_shift' => 'Horas máximas por turno',
        'min_rest_hours' => 'Descanso mínimo entre turnos',
        'min_rest_hours_hint' => 'Evita un cuadrante que acaba a las 23:00 y empieza otra vez a las 6:00.',
        'max_consecutive_days' => 'Días consecutivos máximos de trabajo',

        'allow_overtime' => 'Permitir horas extra',
        'overtime_after_hours' => 'Horas extra a partir de',
        'max_overtime_hours' => 'Horas extra máximas',
        'allow_adjustment' => 'Permitir ajustar el horario',
        'allow_adjustment_hint' => 'Un responsable puede mover un turno generado sin cambiar esta regla.',
        'allow_split_shift' => 'Permitir turno partido del empleado',
        'allow_split_shift_hint' => 'Permite que una persona trabaje más de un periodo separado el mismo día.',
        'allow_same_employee_multiple_periods' => 'Permitir a la misma persona varios periodos',
        'allow_same_employee_multiple_periods_hint' => 'La misma persona puede trabajar más de un periodo al día, siempre que no se solapen.',
        'max_periods_per_employee_per_day' => 'Periodos máximos por persona y día',
        'min_gap_minutes' => 'Descanso mínimo entre turnos partidos',
        'min_gap_hint' => 'Tiempo sin trabajar necesario entre dos periodos de la misma persona.',
        'minutes_unit' => 'minutos',

        'effective_from' => 'Vigente desde',
        'effective_until' => 'Vigente hasta',
        'effective_hint' => 'Déjalas vacías para una regla siempre vigente.',

        'hours_unit' => 'horas',
        'hours_per_week' => 'horas / semana',
        'days_unit' => 'días',
    ],

    'columns' => [
        'day' => 'Día',
        'business_hours' => 'Horario del negocio',
        'name' => 'Regla',
        'location' => 'Ubicación',
        'days' => 'Días de trabajo',
        'hours' => 'Horas por defecto',
        'weekly' => 'Horas semanales',
        'overtime' => 'Horas extra',
        'status' => 'Estado',
        'assigned' => 'Personal',
        'updated' => 'Última actualización',
    ],

    'statuses' => [
        'active' => 'Activa',
        'inactive' => 'Inactiva',
    ],

    'location_scopes' => [
        'all' => 'Todas las ubicaciones',
        'specific' => 'Ubicaciones concretas',
    ],

    'break_types' => [
        'none' => 'Sin descanso',
        'fixed' => 'Descanso fijo',
        'duration' => 'Solo duración',
    ],

    'break_custom' => 'Personalizado',
    'minutes' => ':count min',
    'hours_vary' => 'Varía según el día',
    'no_working_days' => 'Sin días de trabajo',
    'all_locations' => 'Todas las ubicaciones',
    'overtime_allowed' => 'Permitidas',
    'overtime_not_allowed' => 'No permitidas',
    'not_set' => 'Sin definir',

    'assigned_staff' => 'Personal asignado',
    'assigned_count' => '{0} Todavía no hay personal en esta regla|{1} :count persona|[2,*] :count personas',
    'assigned_where' => 'El personal se asigna a una regla desde el formulario de alta o edición de personal. Generar su horario con fechas es cosa de Personal → Horario del personal.',

    'duplicate' => 'Duplicar',
    'activate' => 'Activar',
    'deactivate' => 'Desactivar',
    'deactivate_confirm' => '¿Desactivar :name? Sigue visible en los horarios que ya la usan, pero no se ofrece para los nuevos.',
    'delete_confirm' => '¿Eliminar :name? La regla se borrará de forma permanente.',
    'delete_blocked' => 'A :name la usan :count personas, así que no se puede eliminar. Desactívala: los horarios que la usan necesitan que siga siendo legible.',

    'validation' => [
        'period_name_required' => 'Ponle nombre a cada periodo.',
        'period_times_required' => 'Indica la hora de inicio y de fin de cada periodo.',
        'period_ends_after_starts' => 'Un periodo debe terminar después de empezar.',
        'period_outside_business_hours' => 'El periodo debe caber dentro del horario de trabajo del negocio.',
        'periods_required' => 'Añade al menos un periodo, o desactiva el turno partido.',
        'period_break_too_long' => 'El descanso es más largo que el periodo.',
        'name_required' => 'Ponle un nombre a la regla.',
        'name_taken' => 'Ya existe una regla con ese nombre.',
        'days_required' => 'Elige al menos un día de trabajo.',
        'ends_after_starts' => 'La hora de fin debe ser posterior a la de inicio.',
        'periods_overlap' => 'Los periodos de trabajo del :day se solapan.',
        'split_not_allowed' => 'Esta regla no permite turnos partidos, así que el :day solo puede tener un periodo.',
        'break_too_long' => 'El descanso es más largo que el día de trabajo más corto.',
        'break_minutes_required' => 'Elige cuánto dura el descanso.',
        'break_times_required' => 'Indica el inicio y el fin del descanso fijo.',
        'weekly_hours_positive' => 'Las horas máximas por semana deben ser más de cero.',
        'min_shift_over_max' => 'Las horas mínimas por turno no pueden superar el máximo.',
        'until_before_from' => 'La fecha de fin no puede ser anterior a la de inicio.',
        'locations_required' => 'Elige al menos una ubicación, o haz que la regla se aplique en todas.',
    ],

    'results' => [
        'zero' => 'No se han encontrado reglas de turno',
        'one' => '1 regla de turno encontrada',
        'many' => ':count reglas de turno encontradas',
        'empty' => 'Ninguna regla coincide con tu búsqueda o filtros.',
        'clear' => 'Quitar filtros',
    ],
    'showing' => 'Mostrando :from–:to de :total reglas',
    'none_yet' => 'Todavía no hay reglas de turno',
    'none_yet_hint' => 'Una regla de turno es un patrón que escribes una vez y aplicas a quien quieras: «Jornada completa», «Turno de fin de semana», «Media jornada de mañana».',

    'weekdays_short' => [
        0 => 'Dom',
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
    ],

    'filters' => [
        'all_statuses' => 'Todos los estados',
        'all_locations' => 'Todas las ubicaciones',
    ],

    'hours_editor' => [
        'open' => 'Trabaja',
        'closed' => 'Libre',
        'closed_all_day' => 'No trabaja',
        'add_period' => 'Añadir otro periodo',
        'opening_time' => 'Hora de inicio del :day',
        'closing_time' => 'Hora de fin del :day',
    ],

    'hours_are_global' => 'El horario de trabajo del negocio se usa en toda la programación y las reservas. Cambiarlo aquí lo cambia en todas partes.',
    'edit_business_hours' => 'Editar el horario del negocio',
    'no_location_yet' => 'Añade una ubicación antes de escribir una regla: una semana de trabajo pertenece a una sede.',
    'closed' => 'Cerrado',

    'sections_split' => 'Ajustes de turno partido',
    'split_intro' => 'Divide la jornada del negocio en periodos con nombre para cubrir el día. Cada periodo debe caber dentro del horario de trabajo del negocio.',

    'periods' => [
        'name' => 'Nombre del turno',
        'name_placeholder' => 'Turno de mañana',
        'starts_at' => 'Hora de inicio',
        'ends_at' => 'Hora de fin',
        'break' => 'Descanso',
        'no_break' => 'Sin descanso',
        'status' => 'Estado',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'add' => '+ Añadir periodo',
        'remove' => 'Quitar',
        'minutes' => ':count min',
        'empty' => 'Todavía no hay periodos. Añade el primero para dividir la jornada.',
    ],

    'enable' => 'Activar reglas de turno',
    'enable_hint' => 'Activa reglas reutilizables de programación de personal para este negocio.',
    'feature_on' => 'Las reglas de turno están activadas.',
    'feature_off' => 'Las reglas de turno están desactivadas. No se ha borrado nada.',
    'feature_off_title' => 'Las reglas de turno están desactivadas',
    'feature_off_kept' => '{0} Actívalas para escribir un patrón de trabajo que puedas aplicar a quien quieras.|{1} Tu :count regla sigue aquí: activa las reglas de turno para verla.|[2,*] Tus :count reglas siguen aquí: activa las reglas de turno para verlas.',
    'rule_count' => '{0} Reglas de turno|{1} :count regla de turno|[2,*] :count reglas de turno',
    'back_to_list' => 'Volver a las reglas',
    'delete_title' => '¿Eliminar la regla de turno?',
];
