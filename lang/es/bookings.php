<?php

declare(strict_types=1);

/*
| Citas.
|
| The wording keeps two things apart that are easy to run together: the
| appointment note is about this booking, and the client note is about the
| person. One is read once on the day; the other is read by everybody who
| books them, for as long as they are a client.
*/

return [

    'title' => 'Reservas',
    'intro' => 'Todas las citas tomadas y con quién son.',
    'new' => 'Nueva reserva',
    'new_intro' => 'Busca al cliente o atiende a alguien sin cita.',
    'walk_in_intro' => 'Toma los datos allí mismo en el mostrador.',

    'add' => [
        'client' => 'Añadir cliente',
        'booking' => 'Añadir reserva',
        'walk_in' => 'Añadir reserva · Sin cita',
        'leave' => 'Añadir ausencia',
    ],

    'modes' => [
        'booking' => 'Reserva',
        'walkin' => 'Sin cita',
    ],

    'any_staff' => 'Cualquiera disponible',
    'walk_in_guest' => 'Sin cita',

    'columns' => [
        'reference' => 'ID de reserva',
        'booked_by' => 'Creada por',
        'client' => 'Cliente',
        'date' => 'Fecha',
        'time' => 'Hora',
        'services' => 'Servicios',
        'staff' => 'Con',
        'total' => 'Total',
        'status' => 'Estado',
    ],

    'filters' => [
        'all_statuses' => 'Todos los estados',
        'all_staff' => 'Todo el equipo',
        'date' => 'Fecha',
        'reset' => 'Restablecer',
    ],

    'statuses' => [
        'draft' => ['label' => 'Borrador'],
        'confirmed' => ['label' => 'Confirmada'],
        'arrived' => ['label' => 'Ha llegado'],
        'completed' => ['label' => 'Completada'],
        'no-show' => ['label' => 'No se presentó'],
        'cancelled' => ['label' => 'Cancelada'],
    ],

    'sources' => [
        'front-desk' => 'Mostrador',
        'phone' => 'Teléfono',
        'online' => 'En línea',
        'walk-in' => 'Sin cita',
        'social' => 'Redes sociales',
        'referral' => 'Recomendación',
        'other' => 'Otro',
    ],

    /*
    | The five decisions the booking screen holds, in the order they are
    | usually said out loud rather than the order a database would want them.
    */
    'sections' => [
        'client' => 'Cliente',
        'service' => 'Servicio',
        'when' => 'Con quién y cuándo',
        'details' => 'Detalles de la reserva',
        'payment' => 'Depósito / pago',
        'comms' => 'Comunicación',
        'summary' => 'Resumen de la reserva',
    ],

    'client' => [
        'search' => 'Busca por nombre, teléfono o correo…',
        'search_label' => 'Buscar cliente por nombre, teléfono o correo',
        'or' => 'O',
        'add' => 'Añadir cliente',
        'guest' => 'Reservar sin datos de cliente',
        'none' => 'Ningún cliente coincide.',
        'change' => 'Cambiar',
        'guest_name' => 'Nombre',
        'guest_phone' => 'Móvil',
        'guest_email' => 'Correo',
        'guest_hint' => 'Una reserva sin cita no crea ficha de cliente. Si va a volver, añade al cliente.',
    ],

    'service' => [
        'search' => 'Buscar servicios…',
        'category' => 'Categoría de servicio',
        'all_categories' => 'Todas las categorías',
        'search_categories' => 'Buscar categorías…',
        'chosen' => 'Elegidos',
        'none' => 'Ningún servicio coincide.',
        'empty' => 'Aún no hay servicios. Añade uno y aparecerá aquí.',
        'minutes' => ':count min',
        'remove' => 'Quitar :name',
    ],

    'when' => [
        'staff' => 'Miembro del equipo',
        'any' => 'Cualquiera disponible',
        'date' => 'Fecha',
        'today' => 'Hoy',
        'tomorrow' => 'Mañana',
        'next_3' => 'Próximos 3 días',
        'next_7' => 'Próximos 7 días',
        'custom' => 'Otra fecha',
        'done' => 'Listo',
        'previous_month' => 'Mes anterior',
        'next_month' => 'Mes siguiente',
        'time' => 'Hora de inicio',
        'ends' => 'Termina a las :time',
        'location' => 'Ubicación',
        'morning' => 'Mañana',
        'afternoon' => 'Tarde',
        'evening' => 'Noche',
    ],

    'details' => [
        'source' => 'Origen de la reserva',
        'note' => 'Nota de la cita',
        'note_hint' => 'Solo para esta reserva. Nunca pasa a ser una nota fija del cliente.',
        'note_placeholder' => 'El cliente quiere el mismo corte pero un poco más corto hoy.',
        'client_note' => 'Nota del cliente',
        'client_note_aside' => '— se guarda en la ficha del cliente',
        'client_note_hint' => 'Se guarda en el cliente al confirmar la reserva. La ve todo el que le reserve.',
        'client_note_placeholder' => 'Cuero cabelludo sensible: nada de calor directo en la raíz.',
        'client_note_guest' => 'Una reserva sin cita no tiene ficha donde guardar la nota.',
        'choose_source' => 'Elige un origen',
        'search_sources' => 'Buscar orígenes…',
    ],

    'payment' => [
        'type' => 'Tipo de pago',
        'none' => 'Nada ahora',
        'none_hint' => 'Se paga todo en la cita.',
        'deposit' => 'Cobrar un depósito',
        'deposit_hint' => 'Una parte ahora y el resto el día de la cita.',
        'amount' => 'Importe del depósito',
        'action' => 'Cómo se cobra',
        'actions' => [
            'now' => 'Cobrado en el mostrador',
            'link' => 'Enviar enlace de pago',
            'later' => 'Cobrar al llegar',
            'waive' => 'Exento',
        ],
    ],

    'comms' => [
        'send' => 'Enviar confirmación',
        'both' => 'SMS y correo',
        'sms' => 'SMS',
        'email' => 'Correo',
        'none' => 'Nada',
        'to_sms' => 'SMS a',
        'to_email' => 'Correo a',
        'missing' => 'No hay número ni correo en la ficha, así que no se puede enviar nada.',
    ],

    'summary' => [
        'client' => 'Cliente',
        'services' => 'Servicios',
        'staff' => 'Con',
        'when' => 'Cuándo',
        'duration' => 'Duración',
        'total' => 'Total',
        'deposit' => 'Depósito',
        'due' => 'A pagar el día de la cita',
        'nothing' => 'Aún no has elegido nada.',
        'minutes' => '{1} :count minuto|[2,*] :count minutos',
        'reference' => 'Referencia de la reserva',
        'location' => 'Ubicación',
        'resource' => 'Recurso',
        'starts' => 'Inicio',
        'ends' => 'Fin estimado',
        'subtotal' => 'Subtotal',
        'discount' => 'Descuento',
        'tax' => 'Impuesto (:rate)',
        'tax_included' => 'Impuesto incluido (:rate)',
        'paid' => 'Pagado',
        'estimate' => 'Los precios se confirman al crear la reserva.',
        'status' => 'Estado',
        'date' => 'Fecha',
        'source' => 'Reservada por',
    ],

    'new_client' => [
        'title' => 'Añadir cliente',
        'intro' => 'Lo justo para tomar la reserva. El resto se puede completar luego desde su ficha.',
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'email' => 'Correo electrónico',
        'mobile' => 'Móvil',
        'contact_hint' => 'Un móvil o un correo: uno de los dos, para poder enviar la confirmación.',
        'needs_contact' => 'Un móvil o un correo: la confirmación tiene que ir a algún sitio.',
        'duplicate' => 'Puede que ya sea un cliente.',
        'add' => 'Añadir cliente',
        'add_anyway' => 'Añadir de todas formas',
        'full_form' => 'Formulario completo',
    ],

    'context' => [
        'preferred' => 'Personal preferido',
        'also_seen' => 'También ha visto a',
        'last' => 'Última reserva',
        'again' => 'Reservar lo mismo otra vez',
        'recent' => 'Visitas recientes',
        'average' => 'Valoración media: :rating',
        'preferences' => 'Preferencias de reserva',
        'none' => 'Aún no hay historial: esta es su primera reserva.',
        'visits' => '{0} Sin visitas anteriores|{1} :count visita anterior|[2,*] :count visitas anteriores',
        'from_diary' => 'Deducido del historial',
        'kinds' => [
            'asked_for' => 'Lo pide por su nombre',
            'most_booked' => 'Con quien más reserva',
            'also_seen' => 'Ya le ha atendido',
        ],
        'cadence' => '{1} Reserva más o menos cada semana|[2,*] Reserva más o menos cada :count semanas',
        'windows' => [
            'morning' => 'Prefiere citas por la mañana',
            'afternoon' => 'Prefiere citas por la tarde',
            'evening' => 'Prefiere citas al final del día',
        ],
        'walk_in' => 'Cliente sin cita',
        'walk_in_hint' => 'No se guarda ninguna ficha más allá de esta cita.',
        'remove' => 'Quitar este cliente de la reserva',
    ],

    'confirm' => 'Confirmar reserva',
    'draft' => 'Guardar como borrador',
    'cancel' => 'Cancelar',

    /*
    | What is still missing, said one thing at a time. A list of every
    | unanswered question is a wall; the next one is an instruction.
    */
    'lead' => [
        'created' => 'Referencia de reserva :reference creada. No hace falta hacer nada ahora.',
    ],

    'steps' => [
        'save' => 'Guardar y continuar',
        'edit' => 'Editar',
    ],

    'blockers' => [
        'client' => 'Elige un cliente o tómalo como reserva sin cita.',
        'guest' => 'Pon un nombre a la reserva sin cita.',
        'service' => 'Elige al menos un servicio.',
        'time' => 'Elige una hora de inicio.',
        'ready' => 'Listo para reservar.',
    ],

    'booked' => 'Reserva confirmada para :name.',
    'saved_draft' => 'Reserva guardada como borrador.',

    'empty' => 'Ninguna reserva coincide con estos filtros.',
    'empty_hint' => 'Quita los filtros para ver todas las citas.',
    'none_yet' => 'Aún no hay reservas',
    'none_yet_hint' => 'Toma la primera y aparecerá aquí.',

    'showing' => 'Mostrando :from–:to de :total reservas',
    'results' => [
        'zero' => 'Ninguna reserva',
        'one' => '1 reserva',
        'many' => ':count reservas',
        'clear' => 'Quitar filtros',
    ],
    'actions_for' => 'Acciones para :name',

    'detail' => [
        'payment_status' => 'Pago',
        'book_again' => 'Reservar otra vez',
        'cancel_booking' => 'Cancelar la reserva',
        'reschedule' => 'Reprogramar',
        'soon_hint' => 'Aún no está hecho: cancelar y mover una cita cambian la agenda.',
        'services' => 'Detalle de servicios',
        'notes' => 'Notas de la reserva',
        'no_notes' => 'No se anotó nada sobre esta cita.',
        'payment_summary' => 'Resumen del pago',
        'transactions' => 'Movimientos de pago',
        'no_transactions' => 'Aún no se ha cobrado nada de esta reserva.',
        'recorded_by' => 'registrado por :name',
        'taken_by' => 'Creada por :name el :when',
        'someone' => 'alguien del equipo',
        'updated' => 'última actualización :when',
        'walk_in' => 'Cliente sin cita, reservado sin ficha.',
        'snapshot' => 'Así estaba el :when, cuando se creó esta reserva.',
    ],

    'pay' => [
        'title' => 'Pago',
        'due' => 'Importe a pagar',
        'method' => '¿Cómo van a pagar?',
        'change_method' => 'Cambiar',
        'back' => 'Volver al resumen de la reserva',
        'again' => 'Guardar cambios y continuar',
        'skip' => 'Confirmar sin pago',
        'skip_hint' => 'La reserva se crea y el saldo queda pendiente.',
        'pay_amount' => 'Cobrar :amount',
        'record' => 'Registrar pago en efectivo',
        'mark_paid' => 'Marcar como pagado',
        'marking' => 'Registrando…',
        'amount' => 'Importe',
        'received' => 'Importe recibido',
        'change' => 'Cambio a devolver',
        'reference' => 'Referencia',
        'reference_hint' => 'Lo que muestre el pago por su parte: opcional.',
        'cardholder' => 'Nombre del titular',
        'card_number' => 'Número de tarjeta',
        'expiry' => 'Fecha de caducidad',
        'cvv' => 'CVV',
        'zip' => 'Código postal de facturación',
        'card_safe' => 'Los datos de la tarjeta van directos al proveedor de pago. StyleDesk nunca los guarda.',
        'no_card_provider' => 'No hay proveedor de tarjetas conectado, así que StyleDesk no puede cobrarla. Cóbrala en el datáfono y regístrala abajo.',
        'terminal' => 'Cobrado en el datáfono',
        'not_ready' => 'Aún sin configurar. Añade la cuenta en los ajustes del negocio.',
        'handle_hint' => 'Léelo en voz alta y luego marca el pago como recibido.',
        'short_cash' => 'Es menos que el importe que se está pagando.',
        'failed' => 'No se pudo registrar el pago. No se ha cobrado nada: inténtalo de nuevo.',
        'partial' => ':paid de :total pagado · quedan :due',
    ],

    'methods' => [
        'card' => ['name' => 'Tarjeta de crédito', 'hint' => 'Cobrada ahora o en el datáfono'],
        'cash' => ['name' => 'Efectivo', 'hint' => 'Contado en el mostrador'],
        'paypal' => ['name' => 'PayPal', 'hint' => 'Al PayPal del negocio'],
        'zelle' => ['name' => 'Zelle', 'hint' => 'Al Zelle del negocio'],
        'cash-app' => ['name' => 'Cash App', 'hint' => 'Al Cash App del negocio'],
        'venmo' => ['name' => 'Venmo', 'hint' => 'Al Venmo del negocio'],
    ],

    'payment_statuses' => [
        'unpaid' => ['label' => 'Sin pagar'],
        'partial' => ['label' => 'Pago parcial'],
        'paid' => ['label' => 'Pagado'],
        'pending' => ['label' => 'Pago pendiente'],
        'failed' => ['label' => 'Fallido'],
        'refunded' => ['label' => 'Reembolsado'],
        'partially-refunded' => ['label' => 'Reembolsado en parte'],
    ],

    'confirmation' => [
        'title' => 'Reserva confirmada',
        'made' => 'La cita está en la agenda.',
        'payment' => 'Pago',
        'view' => 'Ver reserva',
        'another' => 'Crear otra reserva',
        'print' => 'Imprimir confirmación',
        'receipt' => 'Descargar recibo',
        'receipt_title' => 'Recibo',
        'send' => 'Enviar confirmación',
        'sending' => 'Enviando…',
        'sent' => 'Confirmación enviada a :to.',
        'no_email' => 'Este cliente no tiene correo en su ficha.',
        'no_sms' => 'Los SMS aún no están conectados. Envíala por correo o añade una cuenta de SMS.',
        'due_notice' => 'Pendiente de pago :amount',
    ],

    'email' => [
        'subject' => ':business: tu cita del :date',
        'headline' => 'Tu cita está confirmada',
        'intro' => 'Gracias, :name. Estos son los detalles.',
    ],

    'validation' => [
        'who' => 'Elige un cliente o pon un nombre a la reserva sin cita.',
    ],
];
