<?php

declare(strict_types=1);

/*
| Fidelidad y recompensas.
|
| Dos públicos, separados por sus claves de primer nivel: `settings` lo lee
| quien decide cómo funciona el programa, `client` quien está en recepción con
| un cliente delante. El segundo es el que tiene que ser breve.
*/

return [

    'title' => 'Fidelidad y recompensas',

    'activities' => [
        'earned' => 'Puntos ganados',
        'redeemed' => 'Recompensa canjeada',
        'expired' => 'Puntos caducados',
        'refund_adjustment' => 'Ajuste por reembolso',
        'cancellation_adjustment' => 'Ajuste por cancelación',
        'manual_add' => 'Adición manual',
        'manual_deduct' => 'Deducción manual',
    ],

    'reasons' => [
        'customer_service' => 'Crédito de atención al cliente',
        'promotion' => 'Promoción',
        'correction' => 'Corrección',
        'duplicate' => 'Puntos duplicados',
        'refund' => 'Ajuste por reembolso',
        'other' => 'Otro',
    ],

    'purchases' => [
        'services' => 'Servicios',
        'products' => 'Productos',
        'memberships' => 'Membresías',
        'packages' => 'Paquetes',
        'gift_cards' => 'Tarjetas regalo',
        'tips' => 'Propinas',
        'taxes' => 'Impuestos',
    ],

    'expiry' => [
        'never' => 'Nunca',
        '6m' => 'A los 6 meses',
        '12m' => 'A los 12 meses',
        '24m' => 'A los 24 meses',
    ],

    'notifications' => [
        'earned_email' => 'Email de puntos ganados',
        'earned_sms' => 'SMS de puntos ganados',
        'reward_email' => 'Email de recompensa disponible',
        'reward_sms' => 'SMS de recompensa disponible',
    ],

    'settings' => [
        'title' => 'Fidelidad y recompensas',
        'intro' => 'Qué gana una visita, cuánto vale un punto al canjearlo y quién puede gastarlo.',

        'enable' => 'Activar fidelidad y recompensas',
        'enable_hint' => 'Los clientes ganan puntos en citas completadas y pagadas, y pueden gastarlos en visitas futuras.',
        'disabled_note' => 'Las recompensas están desactivadas. No se gana nada nuevo ni se puede canjear — todos los saldos y todo el historial se conservan tal cual.',

        'program' => 'Programa',
        'program_hint' => 'El nombre que verán tus clientes en su recibo y en sus correos.',
        'program_name' => 'Nombre del programa',
        'program_name_hint' => 'Por ejemplo: Glow Rewards, Puntos de Belleza, Wellness Rewards.',
        'description' => 'Descripción',
        'description_hint' => 'Una línea que explique el programa. Opcional.',
        'description_placeholder' => 'Gana puntos en cada visita y canjéalos en futuros servicios.',

        'earn' => 'Ganar puntos',
        'earn_hint' => 'Cómo el gasto se convierte en puntos. Los puntos son enteros: con 5 $ = 1 punto, un servicio de 17 $ gana 3.',
        'spend_amount' => 'Gastado',
        'points_earned' => 'Puntos',
        'earn_rule' => ':symbol:amount gastados = :points',
        'eligible' => 'Compras que dan puntos',
        'eligible_hint' => 'Qué genera puntos. Los servicios siempre lo hacen — son de lo que se compone una reserva.',
        'always_on' => 'Siempre activo',
        'coming_soon' => 'Próximamente',

        'redeem' => 'Canjear puntos',
        'redeem_hint' => 'Cuánto vale un punto al canjearlo y los límites para gastar un saldo de una vez.',
        'points_required' => 'Puntos necesarios',
        'reward_value' => 'Valor de la recompensa',
        'minimum_redemption' => 'Puntos mínimos para canjear',
        'minimum_hint' => 'El saldo más pequeño que se puede gastar. Por debajo, al cliente se le dice cuánto le falta.',
        'maximum_reward' => 'Recompensa máxima por transacción',
        'maximum_hint' => 'El descuento máximo en una sola visita. Déjalo vacío para no poner límite.',
        'rule' => ':points puntos = :value',

        'expiry' => 'Caducidad',
        'expiry_hint' => 'Cuánto vive un punto después de ganarse. Los puntos ya ganados conservan la fecha que se les dio.',

        'notifications' => 'Notificaciones',
        'notifications_hint' => 'Qué recibe el cliente cuando su saldo cambia. Todavía no se envía nada — los mensajes llegan en la próxima versión.',

        'rules' => 'Reglas del negocio',
        'rules_hint' => 'Las partes del programa que decide StyleDesk, para que sepas qué esperar.',
        'rule_locations' => 'Un solo saldo para todo el negocio',
        'rule_locations_body' => 'El cliente gana en cualquier local y puede canjear en cualquier otro. No hay saldos separados por sucursal.',
        'rule_awarded' => 'Los puntos llegan cuando la visita está terminada y pagada',
        'rule_awarded_body' => 'Borradores, solicitudes, cancelaciones, rechazos, ausencias y citas sin pagar no generan nada. Una cita pagada en parte gana en proporción a lo liquidado.',
        'rule_refunds' => 'Los reembolsos devuelven los puntos',
        'rule_refunds_body' => 'Un reembolso total revierte todo lo que ganó la visita. Uno parcial revierte la misma proporción y el cliente conserva el resto.',
        'rule_calculation' => 'Los puntos se calculan sobre lo que el cliente pagó realmente',
        'rule_calculation_body' => 'Los cupones, descuentos y recompensas ya gastadas se restan antes de calcular los puntos.',

        'saved' => 'Configuración de fidelidad guardada.',
    ],

    'client' => [
        'title' => 'Fidelidad y recompensas',
        'off' => 'Las recompensas están desactivadas para este negocio.',
        'off_hint' => 'Los saldos y el historial se conservan. No se gana ni se canjea nada hasta que el programa se vuelva a activar.',

        'available' => 'Puntos disponibles',
        'available_hint' => 'Puntos que se pueden canjear ahora.',
        'pending' => 'Puntos pendientes',
        'pending_hint' => 'Previstos de citas todavía sin completar.',
        'lifetime_earned' => 'Ganados en total',
        'lifetime_redeemed' => 'Canjeados en total',

        'reward_value' => 'Valor de la recompensa',
        'worth' => 'Equivale a :value',
        'worth_nothing' => 'Todavía no alcanza para canjear',

        'next_reward' => 'Próxima recompensa',
        'progress' => ':have / :need puntos',
        'to_go' => 'Faltan :points puntos para desbloquear :value',
        'unlocked' => ':value listos para canjear',

        'activity' => 'Actividad de recompensas',
        'none' => 'Todavía no hay actividad de recompensas.',
        'none_filtered' => 'Nada en esta parte del historial.',
        'columns' => [
            'date' => 'Fecha',
            'activity' => 'Actividad',
            'booking' => 'Reserva',
            'location' => 'Local',
            'points' => 'Puntos',
            'balance' => 'Saldo',
        ],
        'filters' => [
            'all' => 'Toda la actividad',
            'earned' => 'Ganados',
            'redeemed' => 'Canjeados',
            'adjustments' => 'Ajustes',
            'expired' => 'Caducados',
            'refunds' => 'Reembolsos',
        ],

        'adjust' => 'Ajustar puntos',
        'adjust_title' => 'Ajustar puntos',
        'adjust_intro' => 'Los puntos añadidos o quitados a mano quedan registrados a tu nombre y no se pueden editar después.',
        'direction' => 'Tipo de ajuste',
        'add' => 'Añadir puntos',
        'remove' => 'Quitar puntos',
        'points' => 'Puntos',
        'reason' => 'Motivo',
        'note' => 'Nota interna',
        'note_hint' => 'Solo la ve tu equipo. Opcional.',
        'save' => 'Guardar ajuste',
        'adjusted' => 'Puntos ajustados.',

        'by' => 'por :name',
        'expires' => 'Caduca el :date',
    ],

    'activity' => [
        'system' => 'StyleDesk',
    ],

];
