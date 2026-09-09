<?php

declare(strict_types=1);

/*
| Asignar un horario de trabajo a una persona. Ver lang/en/schedule.php para
| el porqué de cada línea.
*/

return [

    'year' => 'Año',
    'monthly_summary' => 'Resumen mensual',
    'month_hours' => 'Horas',
    'month_shifts' => 'Turnos',
    'month_days' => 'Días de trabajo',
    'bookings_pending' => 'El número de reservas llegará con el módulo de reservas.',

    'working_schedule' => 'Horario de trabajo',
    'assign' => 'Asignar horario',
    'assign_title' => 'Asignar horario',
    'assign_intro' => 'Asigna un horario de trabajo para esta persona y el rango de fechas elegido.',
    'assign_for' => 'Miembro del personal',
    'period' => 'Periodo',
    'assigning' => 'Asignando…',

    'add_shift' => 'Añadir turno',
    'add_for_day' => 'Añadir horario',
    'delete_day' => 'Eliminar horario',
    'delete_day_title' => '¿Eliminar el horario?',
    'delete_day_confirm' => 'Se quitará el horario de trabajo de :name del :date.',
    'day_deleted' => 'Horario del :date eliminado.',
    'mark_on_leave' => 'Marcar como ausencia — Próximamente',
    'showing' => 'Mostrando :from–:to de :total días',
    'actions_for' => 'Acciones para :name',

    'filters' => [
        'period' => 'Periodo',
        'month' => 'Mes',
        'year' => 'Año',
        'apply' => 'Aplicar',
        'edit_period' => 'Cambiar mes',
        'reset' => 'Restablecer',
    ],

    'periods' => [
        'month' => 'Mes',
        '3-months' => '3 meses',
        '6-months' => '6 meses',
        '9-months' => '9 meses',
        'year' => 'Año',
    ],

    'columns' => [
        'date' => 'Fecha',
        'day' => 'Día',
        'working' => 'Estado laboral',
        'time' => 'Horario',
        'status' => 'Estado',
        'published_on' => 'Publicado el',
        'published_by' => 'Publicado por',
        'hours' => 'Horas totales',
        'bookings' => 'Reservas',
    ],

    'empty' => 'No se encontró ningún horario',
    'empty_hint' => 'No hay registros de horario para el periodo seleccionado.',

    'duration_intro' => 'El mes se planifica entero. Podrás cambiar cualquier día cuando se abra el horario.',
    'continue_label' => 'Continuar',
    'back' => 'Volver',
    'close' => 'Cerrar',
    'save' => 'Guardar',
    'save_and_publish' => 'Guardar y publicar',
    'update_and_publish' => 'Actualizar y publicar',
    'rule_change_title' => '¿Rehacer los días con esta regla?',
    'rule_change_confirm' => 'Los días de abajo se volverán a rellenar con la regla elegida y se perderán los cambios que hayas hecho aquí.',
    'rule_change_label' => 'Rehacer los días',
    'leave_title' => '¿Salir sin guardar?',
    'leave_confirm' => 'Este horario tiene cambios sin guardar. Si sales ahora se descartan.',
    'leave_confirm_label' => 'Descartar cambios',
    'needs_javascript' => 'Crear un horario necesita JavaScript, que está desactivado en este navegador.',

    'summary' => '{0} Nada programado|{1} :hours horas programadas · :count día de trabajo|[2,*] :hours horas programadas · :count días de trabajo',
    'hours_short' => ':count h',

    'working' => 'Trabaja',
    'off' => 'Libre',
    'not_working' => 'No trabaja',
    'week_number' => 'Semana :number',
    'add_period' => '+ Añadir periodo',
    'remove_period' => 'Quitar este periodo',
    'starts_at' => 'Inicio',
    'ends_at' => 'Fin',
    'break' => 'Descanso',
    'no_break' => 'Sin descanso',

    'assigned' => '{1} :count turno asignado.|[2,*] :count turnos asignados.',
    'nothing_to_assign' => 'No se eligió ningún día de trabajo, así que no se asignó nada.',
    'replaced_note' => 'Asignar sustituye los turnos que ya haya en este rango.',

    'shift_rule' => 'Regla de turno',
    'no_shift_rule' => 'Sin regla de turno',
    'change_rule' => 'Cambiar',
    'prefill_hint' => 'Elegir una regla rellena los días de abajo con el horario del negocio y los periodos de la regla. Cambiar un día aquí solo cambia este horario, nunca la regla.',

    'refused' => '{1} El horario no se guardó: un día incumple la regla de turno.|[2,*] El horario no se guardó: :count días incumplen la regla de turno.',
    'refused_title' => 'Este horario no se guardó.',

    'publish_statuses' => [
        'draft' => 'Borrador',
        'published' => 'Publicado',
    ],

    /*
    | Publicación.
    |
    | Asignar un horario y comunicarlo son dos actos distintos, y el texto es
    | donde se marca esa diferencia: un borrador es la persona responsable
    | todavía pensando; publicado es la semana de trabajo del empleado.
    */
    'draft_badge' => 'Horario en borrador',
    'published_badge' => 'Publicado',
    'published_notice_title' => 'Este horario ya se ha publicado.',
    'published_notice' => 'Cualquier cambio que hagas aquí actualiza el horario publicado de :name. Cuando vuelvas a publicarlo, se le enviará un correo para avisarle de que su horario ha cambiado. Guardarlo como borrador no le avisa de nada.',
    'locked' => 'Publicado: ya se ha informado al miembro del personal de este día.',
    'published_on' => 'Publicado el :date',
    'published_by' => 'por :name',
    'changes_badge' => 'Cambios sin publicar',
    'changes_hint' => 'Este horario se publicó el :date y se ha editado desde entonces. :name no sabe nada de los cambios.',
    'draft_hint' => 'Todavía no se ha enviado nada. :name solo recibirá un correo cuando publiques.',
    'published_hint' => 'Se envió este horario a :name por correo.',

    'save_draft' => 'Guardar borrador',
    'publish' => 'Publicar horario',
    'publish_changes' => 'Publicar cambios',
    'publishing' => 'Publicando…',

    'draft_saved' => 'Horario guardado como borrador.',
    'published' => 'Horario publicado correctamente. Se ha avisado a :name por correo.',
    'republished' => 'Horario actualizado y publicado. Se ha avisado a :name.',
    'published_without_email' => 'Horario publicado. :name no tiene correo registrado, así que no se envió ningún aviso.',
    'published_email_failed' => 'Horario publicado, pero no se ha podido enviar el correo a :name. El fallo ha quedado registrado.',
    'nothing_to_save' => 'No hay nada programado en este periodo que guardar.',
    'nothing_to_publish' => 'No hay nada programado en este periodo que publicar.',

    'confirm' => [
        'title' => '¿Publicar el horario del empleado?',
        'intro' => 'Revisa el horario antes de publicarlo. Una vez publicado, se avisará al empleado por correo.',
        'staff_member' => 'Empleado',
        'period' => 'Periodo del horario',
        'duration' => 'Duración',
        'working_days' => 'Días trabajados',
        'total_hours' => 'Horas programadas',
        'weeks' => '{1} 1 semana|[2,*] :count semanas',
        'hours' => '{1} :count hora|[2,*] :count horas',
        'days' => ':count',
        'days_long' => '{1} 1 día|[2,*] :count días',
        'will_notify' => 'El horario se publicará y se avisará al empleado por correo.',
        'consequence' => 'Una vez publicado, este horario pasa a ser el horario de trabajo oficial del empleado y se le avisará por correo.',
    ],

    'confirm_draft' => [
        'title' => '¿Guardar el horario como borrador?',
        'intro' => 'El horario se guardará, pero no se enviará al empleado hasta que se publique.',
    ],

    'day_total' => '{1} :count hora|[2,*] :count horas',

    'email' => [
        'subject' => 'Se ha publicado tu horario de trabajo',
        'subject_updated' => 'Tu horario de trabajo se ha actualizado',
        'headline_updated' => 'Tu horario de trabajo se ha actualizado',
        'intro_updated' => 'Esto sustituye al horario que te enviamos antes. Aquí lo tienes completo.',
        'preheader' => 'Tu horario del :from al :until.',
        'headline' => 'Se ha publicado tu horario de trabajo',
        'greeting' => 'Hola :name:',
        'intro' => ':business ha publicado tu horario de trabajo. Aquí lo tienes completo.',
        'period' => 'Periodo del horario',
        'location' => 'Ubicación',
        'working_days' => 'Días trabajados',
        'total_hours' => 'Horas programadas',
        'published_on' => 'Publicado',
        'published_by' => 'Publicado por',
        'hours' => '{1} :count hora|[2,*] :count horas',
        'daily_schedule' => 'Horario diario',
        'not_working' => 'No trabaja',
        'day_total' => '{1} Total: :count hora|[2,*] Total: :count horas',
        'cta' => 'Ver mi horario',
        'questions' => 'Si algo no cuadra, habla con tu responsable en :business.',
    ],

    'validation' => [
        'staff_not_active' => ':name no está activo, así que no se le puede asignar horario.',
        'ends_after_starts' => 'La hora de fin debe ser posterior a la de inicio.',
        'periods_overlap' => 'Los periodos de trabajo de este día se solapan.',
        'split_not_allowed' => 'La regla asignada no permite turnos partidos, así que este día solo puede tener un periodo.',
        'one_period_only' => 'La regla asignada no permite que la misma persona trabaje más de un periodo al día.',
        'too_many_periods' => 'La regla asignada permite como máximo :count periodos al día.',
        'gap_too_short' => 'La regla asignada exige :hours horas entre turnos partidos.',
        'business_closed' => 'El negocio cierra ese día.',
        'outside_business_hours' => 'Fuera del horario de trabajo del negocio (:hours).',
        'over_daily_hours' => 'Más de las :count horas diarias que permite la regla.',
        'over_weekly_hours' => 'Más de las :count horas semanales que permite la regla.',
        'not_enough_rest' => 'Menos de las :count horas de descanso que exige la regla desde el turno anterior.',
        'too_many_consecutive' => 'Más de :count días consecutivos de trabajo, que la regla no permite.',
        'already_working' => 'Ya trabaja :hours ese día.',
    ],
];
