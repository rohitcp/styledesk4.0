<?php

declare(strict_types=1);

return [

    'title' => 'Pagos',
    'intro' => 'Si aceptas pagos, quién los procesa y qué métodos admites.',

    'enable' => 'Activar pagos',
    'enable_hint' => 'Cuando está desactivado, StyleDesk no registra ningún importe en las reservas y el cobro queda oculto.',

    'processor' => 'Procesador de pagos',
    'processor_hint' => 'Un único procesador gestiona los pagos con tarjeta. Elijas el que elijas, podrás seguir registrando efectivo y transferencias.',
    'active' => 'ACTIVO',
    'coming_soon' => 'Próximamente',
    'use_this' => 'Usar este',

    'gateways' => [
        'manual' => [
            'name' => 'Solo registrar pagos',
            'description' => 'Dinero que llega por otra vía: efectivo, transferencia bancaria o una tarjeta cobrada en tu propio datáfono. StyleDesk anota que ha llegado; no cobra a nadie.',
            'unavailable' => '',
        ],
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Acepta tarjetas, Apple Pay y Google Pay en línea y en el mostrador, con los ingresos en tu propia cuenta bancaria.',
            'unavailable' => '',
        ],
        'square' => [
            'name' => 'Square',
            'description' => 'Conecta la cuenta de Square que ya usas, incluidos tus lectores y terminales actuales.',
            'unavailable' => 'Todavía no está disponible. StyleDesk lo está desarrollando.',
        ],
    ],

    /* Card brands, as a person writes them rather than as a gateway keys
       them. Anything not listed falls back to its own key, tidied up — a new
       brand should read as itself rather than as nothing. */
    'methods_list' => [
        'no_vault' => 'No hay ninguna pasarela de pago conectada, así que no se pueden guardar tarjetas.',
        'default_set' => ':card es ahora el método de pago predeterminado.',
        'removed' => 'Se ha quitado :card.',
        'title' => 'Métodos de pago',
        'none' => 'No hay tarjetas guardadas',
        'none_hint' => 'Una tarjeta guardada aquí se puede cobrar para renovaciones de membresía sin el cliente presente.',
        'default' => 'Predeterminada',
        'make_default' => 'Establecer como predeterminada',
        'expires' => 'Caduca el :date',
        'expired' => 'Caducada',
        'expiring' => 'Caduca este mes',
        'needs_attention' => 'El método de pago necesita atención',
        'add' => 'Añadir tarjeta',
        'remove' => 'Quitar tarjeta',
        'remove_confirm' => '¿Quitar esta tarjeta? Ya no se podrá cobrar en ella.',
        'in_use' => 'Esta tarjeta renueva :name. Elige otro método de pago antes de quitarla.',
        'used_by' => 'Renueva :name',
        'gateway' => 'Procesado por :name',
        'statuses' => [
            'active' => 'Activa',
            'expired' => 'Caducada',
            'removed' => 'Quitada',
        ],
    ],

    'brands' => [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express',
        'discover' => 'Discover',
        'diners' => 'Diners Club',
        'jcb' => 'JCB',
        'unionpay' => 'UnionPay',
    ],

    'stripe' => [
        'not_settled' => 'No se cobró la tarjeta. El pago no se liquidó.',
        'connect' => 'Conectar Stripe',
        'continue' => 'Continuar configuración',
        'manage' => 'Gestionar cuenta',
        'disconnect' => 'Desconectar',
        'not_connected' => 'Aún no hay ninguna cuenta de Stripe conectada.',
        'no_account' => 'No hay ninguna cuenta de Stripe que abrir.',
        'payout_account' => 'Ingresos a •••• :last4',
        'no_payout_account' => 'Todavía no hay cuenta bancaria para los ingresos',
        'connected' => 'Stripe está conectado. Ya puedes aceptar pagos con tarjeta.',
        'still_needed' => 'Stripe necesita algunos datos más antes de que puedas aceptar pagos.',
        'disconnected' => 'Stripe se ha desconectado. Puedes seguir registrando efectivo y transferencias.',
        'failed' => 'No se ha podido contactar con Stripe. :reason',
        'not_ready' => 'Este negocio todavía no puede aceptar pagos con tarjeta.',
        'outstanding' => 'Stripe todavía necesita: :fields',
        'refunded_at_stripe' => 'Reembolsado desde el panel de Stripe.',
        'modes' => [
            'platform' => 'Conectado a través de StyleDesk',
            'own' => 'Tu propia cuenta de Stripe',
        ],
        'use_own' => 'Usar mi propia cuenta de Stripe',
        'replace_keys' => 'Sustituir mis claves de Stripe',
        'use_own_hint' => '¿Ya usas Stripe? Pega tus claves y StyleDesk usará tu cuenta directamente. Los pagos, los ingresos y las disputas quedan enteramente entre Stripe y tú.',
        'secret_key' => 'Clave secreta',
        'publishable_key' => 'Clave publicable',
        'save_keys' => 'Guardar y verificar',
        'keys_saved' => 'Tus claves de Stripe se han guardado y verificado.',
        'key_rejected' => 'Stripe no ha aceptado esa clave. :reason',
        'key_empty' => 'No se ha indicado ninguna clave.',
        'key_warning' => 'Una clave secreta puede cobrar, reembolsar y leer todo lo que hay en tu cuenta de Stripe. StyleDesk la cifra y no vuelve a mostrarla: trátala como una contraseña y usa una clave restringida si prefieres limitar lo que StyleDesk puede hacer.',
        'platform_not_configured' => 'StyleDesk no está configurado para dar de alta cuentas de Stripe.',
        'platform_unavailable' => 'Conectar a través de StyleDesk no está disponible en esta instalación. Aun así puedes usar tu propia cuenta de Stripe más abajo.',
        'statuses' => [
            'connected' => 'Conectado',
            'needs_attention' => 'Requiere atención',
            'incomplete' => 'Configuración incompleta',
        ],
        /* Se dice una sola vez, porque es lo que más quiere saber un
           propietario y la razón por la que se eligió Connect en lugar de una
           única cuenta de comercio compartida. */
        'money_note' => 'Los pagos van directamente a tu propia cuenta de Stripe y a tu propio banco. StyleDesk nunca retiene tu dinero.',
    ],

    'methods' => 'Métodos de pago aceptados',
    'methods_hint' => 'Lo que tu equipo puede elegir en la caja. Algunos los procesa tu procesador de tarjetas; el resto se cobra por otra vía y se registra aquí.',
    'needs_processor' => 'Requiere un procesador de tarjetas conectado',

    'method_names' => [
        'card' => 'Tarjeta de crédito o débito',
        'cash' => 'Efectivo',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'gift_card' => 'Tarjeta regalo',
        'store_credit' => 'Saldo a favor',
        'paypal' => 'PayPal',
        'zelle' => 'Zelle',
        'venmo' => 'Venmo',
        'cash-app' => 'Cash App',
        'external' => 'Otro pago externo',
    ],

    'deposit' => 'Depósito de reserva',
    'deposit_hint' => 'Lo que pides por adelantado al tomar una reserva.',
    'deposit_type' => 'Depósito',
    'deposit_value' => 'Importe',
    'deposit_types' => [
        'none' => 'Sin depósito',
        'fixed' => 'Importe fijo',
        'percent' => 'Porcentaje de la reserva',
    ],
    /* Los tres niveles, dichos una vez. Un servicio que pide su propio
       depósito y una reserva que anula ambos son los otros dos, y quien no
       conozca el orden no entenderá por qué un servicio ignora esto. */
    'deposit_levels' => 'Este es el valor por defecto. Un servicio puede pedir el suyo propio, y una reserva concreta puede anular ambos.',

    'save' => 'Guardar',
    'saved' => 'Tus ajustes de pago se han guardado.',
];
