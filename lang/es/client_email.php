<?php

declare(strict_types=1);

return [

    'title' => 'Correo',
    'intro' => 'Envía correos a tus clientes desde StyleDesk.',

    'settings' => [
        'enable' => 'Activar el correo a clientes',
        'enable_hint' => 'Cuando está desactivado, «Enviar correo» se oculta en las fichas de cliente. El historial se conserva.',
        'enabled' => 'Activado',
        'disabled' => 'Desactivado',

        'default_method' => 'Método de envío predeterminado',
        'default_hint' => 'Un solo proveedor envía tu correo a clientes. Puedes cambiarlo cuando quieras.',
        'active' => 'ACTIVO',
        'coming_soon' => 'Próximamente',
        'use_this' => 'Usar este',

        'sender' => 'Remitente',
        'sender_hint' => 'Cómo se firman tus correos, sea cual sea el método de envío.',
        'sender_name' => 'Nombre del remitente',
        'sender_name_hint' => 'El nombre que ven tus clientes. Por defecto, el de tu negocio.',
        'reply_to' => 'Correo de respuesta',
        'reply_to_hint' => 'A dónde llega la respuesta del cliente. Sin él, las respuestas no llegan a nadie.',
        'reply_to_gmail' => 'No se usa mientras envía Gmail: las respuestas llegan directamente a tu bandeja conectada.',
        'preview' => 'Tus clientes verán',

        'send_test' => 'Enviar correo de prueba',
        'test_hint' => 'Se envía a tu propia dirección, para que veas exactamente lo que recibe un cliente.',
        'test_sent' => 'Correo de prueba enviado a :email.',
        'test_failed' => 'No se ha podido enviar la prueba. :reason',
        'test_subject' => 'Correo de prueba de StyleDesk',
        'test_body' => "Esto es un correo de prueba de :name.\n\nSi puedes leerlo, tu correo a clientes está configurado y funciona. No se ha enviado nada a ninguno de tus clientes.",

        'saved' => 'Se ha guardado la configuración de correo.',
        'save' => 'Guardar',
    ],

    'providers' => [
        'styledesk' => [
            'name' => 'Correo de StyleDesk',
            'description' => 'Envía correos directamente a través de StyleDesk sin conectar una cuenta externa.',
        ],
        'gmail' => [
            'name' => 'Conectar Gmail',
            'description' => 'Conecta la cuenta de Gmail o Google Workspace de tu negocio y envía desde tu dirección de siempre.',
        ],
    ],

    'connection' => [
        'connected' => 'Conectado',
        'disconnected' => 'Desconectado',
        'needs_attention' => 'Requiere atención',
    ],

    'send' => [
        'action' => 'Enviar correo',
        'title' => 'Enviar correo',
        'to' => 'Para',
        'from' => 'De',
        'from_via' => ':name mediante StyleDesk',
        'template' => 'Plantilla',
        'no_template' => 'Sin plantilla',
        'related_booking' => 'Reserva relacionada',
        'no_booking' => 'Ninguna',
        'subject' => 'Asunto',
        'message' => 'Mensaje',
        'cancel' => 'Cancelar',
        'submit' => 'Enviar correo',
        'sending' => 'Enviando…',
        'sent' => 'Correo enviado a :name',
    ],

    'statuses' => [
        'queued' => 'En cola',
        'sent' => 'Enviado',
        'delivered' => 'Entregado',
        'failed' => 'Fallido',
    ],

    'history' => [
        'title' => 'Historial de correos',
        'empty' => 'Todavía no se ha enviado ningún correo a este cliente.',
        'sent_by' => 'Enviado por :name',
        'system' => 'Sistema StyleDesk',
        'view' => 'Ver',
    ],

    'errors' => [
        'disabled' => 'El correo a clientes está desactivado para este negocio. Actívalo en Configuración → Correo.',
        'no_provider' => 'No hay ningún método de envío configurado. Elige uno en Configuración → Correo.',
        'no_address' => 'Este cliente no tiene ninguna dirección de correo registrada.',
        'failed' => 'No se ha podido enviar el correo. Queda registrado como fallido en la ficha del cliente y puedes volver a intentarlo.',
        'gmail_not_connected' => 'No hay ninguna cuenta de Gmail conectada. Conecta una en Configuración → Correo.',
        'reconnect_gmail' => 'Volver a conectar Gmail',
    ],

    'templates' => [
        'appointment_follow_up' => [
            'name' => 'Seguimiento de la cita',
            'subject' => 'Gracias por visitar {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\nGracias por venir el {{booking_date}}. Esperamos que estés content@ con tu {{service_name}}.\n\nSi quieres ajustar cualquier cosa, responde a este correo y nos encargamos.\n\nUn saludo,\n{{business_name}}",
        ],
        'appointment_information' => [
            'name' => 'Información de la cita',
            'subject' => 'Tu cita en {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\nTe escribimos sobre tu cita del {{booking_date}} a las {{booking_time}} con {{staff_name}}.\n\nReferencia: {{booking_reference}}\n\nSi necesitas cambiar algo, responde a este correo o llámanos.\n\nUn saludo,\n{{business_name}}",
        ],
        'payment_reminder' => [
            'name' => 'Recordatorio de pago',
            'subject' => 'Recordatorio sobre tu saldo en {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\nTe recordamos que tu saldo pendiente es de {{balance_due}}.\n\nPuedes liquidarlo en tu próxima visita, o responder a este correo y te enviamos un enlace de pago.\n\nUn saludo,\n{{business_name}}",
        ],
        'outstanding_balance' => [
            'name' => 'Saldo pendiente',
            'subject' => 'Saldo pendiente de tu visita del {{booking_date}}',
            'body' => "Hola {{client_first_name}}:\n\nTu visita del {{booking_date}} tiene un saldo pendiente de {{balance_due}}.\n\nReferencia: {{booking_reference}}\n\nSi crees que es un error, respóndenos y lo revisamos enseguida.\n\nUn saludo,\n{{business_name}}",
        ],
        'thank_you' => [
            'name' => 'Agradecimiento',
            'subject' => 'Gracias de parte de {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\nGracias por elegir {{business_name}}. Ha sido un placer atenderte.\n\nEsperamos verte pronto.\n\nUn saludo,\n{{business_name}}",
        ],
        'service_follow_up' => [
            'name' => 'Seguimiento del servicio',
            'subject' => '¿Qué tal tu {{service_name}}?',
            'body' => "Hola {{client_first_name}}:\n\nHa pasado un tiempo desde tu {{service_name}} con {{staff_name}} y queríamos saber qué tal te va.\n\nSi quieres un retoque o tienes cualquier duda, responde a este correo.\n\nUn saludo,\n{{business_name}}",
        ],
        'membership_information' => [
            'name' => 'Información de membresía',
            'subject' => 'Membresía en {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\nQueríamos contarte nuestras opciones de membresía, que ofrecen mejores precios y reserva prioritaria a los clientes habituales.\n\nResponde a este correo y te enviamos los detalles.\n\nUn saludo,\n{{business_name}}",
        ],
        'general_message' => [
            'name' => 'Mensaje general',
            'subject' => 'Un mensaje de {{business_name}}',
            'body' => "Hola {{client_first_name}}:\n\n\nUn saludo,\n{{business_name}}",
        ],
    ],

    'gmail' => [
        'connect' => 'Conectar Gmail',
        'reconnect' => 'Volver a conectar',
        'disconnect' => 'Desconectar',
        'connected' => 'Gmail conectado. Tu correo a clientes se envía ahora desde :email.',
        'connected_on' => 'Conectado el :date',
        'disconnected' => 'Se ha desconectado Gmail. El correo a clientes se envía ahora con el correo de StyleDesk.',
        'failed' => 'No se ha podido conectar Gmail. :reason',
        'state_mismatch' => 'No se ha podido verificar ese intento de conexión. Inténtalo de nuevo.',
        'no_code' => 'Google no ha devuelto nada con lo que conectar.',
        'no_refresh_token' => 'Google no ha emitido un permiso duradero para esta cuenta.',
        'reconnect_needed' => 'La conexión con Gmail ha dejado de funcionar y hay que volver a conectarla.',
        'unknown_error' => 'Google no ha indicado el motivo.',
        'replies_note' => 'Los clientes responden directamente a esta bandeja. Las respuestas no se importan a StyleDesk.',
    ],

    'variables' => [
        'title' => 'Puedes usar',
        'hint' => 'Se sustituyen al cargar la plantilla.',
    ],
];
