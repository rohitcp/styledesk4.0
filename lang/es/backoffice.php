<?php

declare(strict_types=1);

return [

    'title' => 'StyleDesk Backoffice',
    'subtitle' => 'Administración de la plataforma',

    'nav' => [
        'label' => 'Secciones del Backoffice',
        'dashboard' => 'Panel',
        'clients' => 'Clientes',
        'plans' => 'Planes',
        'billing' => 'Pagos y facturación',
        'settings' => 'Ajustes de la plataforma',
        'profile' => 'Mi perfil',
        'logout' => 'Cerrar sesión',
    ],

    'roles' => [
        'super-owner' => 'Propietario principal',
        'admin' => 'Administrador',
        'billing-admin' => 'Administrador de facturación',
        'support-admin' => 'Administrador de soporte',
        'read-only' => 'Solo lectura',
    ],

    'auth' => [
        'restricted' => 'Solo para administradores de StyleDesk.',

        'email_title' => 'Iniciar sesión',
        'email_intro' => 'Escribe tu dirección y te enviaremos un código de un solo uso.',
        'email_label' => 'Correo electrónico',
        'send_code' => 'Enviar código',

        'code_title' => 'Revisa tu correo',
        'code_intro' => 'Si :email pertenece a un administrador, el código va en camino. Es válido durante un rato.',
        'code_label' => 'Código de verificación',
        'verify_code' => 'Verificar código',
        'resend_code' => 'Reenviar código',
        'change_email' => 'Usar otra dirección',
        'code_sent' => 'Si esa dirección pertenece a un administrador, se ha enviado un código.',
        'code_wrong' => 'Ese código no es correcto o ha caducado. Pide uno nuevo.',
        'too_many_codes' => 'Se han pedido demasiados códigos. Espera unos minutos e inténtalo de nuevo.',

        'login_title' => 'Escribe tu contraseña',
        'login_intro' => 'Tu dirección está verificada. Un paso más.',
        'password_label' => 'Contraseña',
        'remember' => 'Recordarme',
        'forgot' => '¿Olvidaste la contraseña?',
        'sign_in' => 'Entrar',
        'refused' => 'Esos datos no se han aceptado.',
        'throttled' => 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.',

        'timed_out' => 'Se cerró tu sesión tras un periodo de inactividad.',
        'disabled' => 'Esta cuenta de administrador ya no está activa.',

        'forgot_title' => 'Restablecer la contraseña',
        'forgot_intro' => 'Enviaremos un enlace si la dirección pertenece a un administrador.',
        'send_reset' => 'Enviar enlace',
        'reset_sent' => 'Si esa dirección pertenece a un administrador, se ha enviado un enlace.',
        'back_to_sign_in' => 'Volver al inicio de sesión',

        'reset_title' => 'Elige una contraseña nueva',
        'new_password' => 'Contraseña nueva',
        'confirm_password' => 'Confirmar contraseña',
        'reset_submit' => 'Guardar contraseña',
        'reset_done' => 'Tu contraseña ha cambiado. Entra con ella.',
    ],

    'email' => [
        'code_subject' => 'Tu código de acceso al Backoffice de StyleDesk',
        'code_headline' => 'Tu código de acceso',
        'code_preheader' => 'Un código de un solo uso para el Backoffice de StyleDesk.',
        'code_greeting' => 'Hola :name:',
        'code_intro' => 'Usa este código para continuar con el inicio de sesión en el Backoffice.',
        'code_expiry' => 'El código deja de funcionar a los :minutes minutos.',
        'code_unexpected' => 'Si no has pedido entrar, alguien más tiene tu dirección. Avisa al equipo y cambia la contraseña.',

        'reset_subject' => 'Restablece tu contraseña del Backoffice de StyleDesk',
        'reset_headline' => 'Restablece tu contraseña',
        'reset_preheader' => 'Un enlace para elegir una nueva contraseña del Backoffice.',
        'reset_greeting' => 'Hola :name:',
        'reset_intro' => 'Alguien ha pedido restablecer la contraseña de tu cuenta del Backoffice de StyleDesk. Usa el botón de abajo para elegir una nueva.',
        'reset_cta' => 'Elegir una nueva contraseña',
        'reset_expiry' => 'El enlace deja de funcionar a los :minutes minutos y solo se puede usar una vez.',
        'reset_fallback' => 'Si el botón no funciona, copia y pega esta dirección en tu navegador:',
        'reset_unexpected' => 'Si no lo has pedido, puedes ignorar este correo: tu contraseña no cambia. Si sigue ocurriendo, alguien tiene tu dirección: avisa al equipo.',
    ],

    'clients' => [
        'title' => 'Clientes',
        'intro' => 'Todos los negocios suscritos a StyleDesk.',

        'stats' => [
            'total' => 'Negocios',
            'active' => 'Activos',
            'trialing' => 'En prueba',
            'new_this_month' => 'Nuevos este mes',
            'services' => 'Servicios',
            'users' => 'Usuarios',
            'bookings' => 'Reservas',
        ],

        'search_label' => 'Buscar',
        'search_placeholder' => 'Negocio, slug, correo o propietario',
        'status' => 'Estado',
        'any' => 'Cualquiera',
        'apply' => 'Aplicar',
        'clear' => 'Limpiar',

        'col' => [
            'business' => 'Negocio',
            'status' => 'Estado',
            'owner' => 'Propietario',
            'plan' => 'Plan',
            'locations' => 'Ubicaciones',
            'staff' => 'Personal',
            'clients' => 'Clientes',
            'joined' => 'Alta',
        ],

        'showing' => 'Mostrando :first–:last de :total',

        'breadcrumb' => 'Ruta de navegación',
        'joined_on' => 'Alta el :date',
        'contact' => 'Contacto',
        'regional' => 'Configuración regional',
        'business_email' => 'Correo del negocio',
        'business_phone' => 'Teléfono del negocio',
        'website' => 'Sitio web',
        'country' => 'País',
        'currency' => 'Moneda',
        'timezone' => 'Zona horaria',
        'language' => 'Idioma predeterminado',
        'identifier' => 'ID de inquilino',
        'owner_name' => 'Nombre',
        'owner_email' => 'Correo',
        'owner_phone' => 'Teléfono',
        'subscription' => 'Suscripción',
        'trial_started' => 'Prueba iniciada',
        'trial_ends' => 'Fin de la prueba',
        'location_name' => 'Ubicación',
        'location_where' => 'Dónde',
        'primary' => 'Principal',
        'last_seen' => 'Último acceso',
        'never' => 'Nunca',
        'no_locations' => 'Todavía no hay ubicaciones.',

        'statuses' => [
            'active' => 'Activo',
            'trial' => 'Prueba',
            'past_due' => 'Pago vencido',
            'disabled' => 'Desactivado',
            'cancelled' => 'Cancelado',
        ],

        'reasons' => [
            'non_payment' => 'Impago / Factura vencida',
            'payment_failed' => 'Pagos fallidos repetidos',
            'subscription_cancelled' => 'Suscripción cancelada',
            'trial_expired' => 'Prueba caducada',
            'chargeback' => 'Contracargo / Disputa de pago',
            'tos_violation' => 'Incumplimiento de las condiciones del servicio',
            'fraud' => 'Fraude / Actividad sospechosa',
            'client_requested' => 'Cierre solicitado por el cliente',
            'business_closed' => 'Negocio cerrado definitivamente',
            'duplicate_account' => 'Cuenta duplicada',
            'compliance' => 'Problema de cumplimiento',
            'administrative' => 'Suspensión administrativa',
            'other' => 'Otro',
        ],

        'choose_reason' => 'Elige un motivo',
        'note' => 'Nota',
        'optional' => '(opcional)',
        'note_placeholder' => 'La factura n.º INV-2048 lleva más de 45 días sin pagar. Se enviaron varios recordatorios de pago.',
        'note_internal' => 'Solo para uso interno. El cliente nunca la ve.',
        'note_required' => 'Se requiere una nota cuando el motivo es Otro.',
        'enable_note_placeholder' => 'Por qué se restablece esta cuenta.',
        'cancel' => 'Cancelar',
        'by' => 'Por',
        'activity' => 'Actividad del Backoffice',
        'no_activity' => 'Todavía no hay nada registrado para este cliente.',
        'disable_confirm_title' => '¿Desactivar este cliente?',
        'disable_confirm_body' => 'Al desactivar el cliente, los usuarios de :name no podrán acceder a StyleDesk. No se elimina nada y la cuenta se puede volver a activar.',
        'enable_confirm_title' => '¿Activar este cliente?',
        'enable_confirm_body' => 'Los usuarios de :name podrán iniciar sesión de nuevo.',
        'disable_action' => 'Desactivar cliente',
        'disable_reason' => 'Motivo',
        'disabled_done' => ':name ha sido desactivado. Nadie de ese negocio puede iniciar sesión.',
        'enable_action' => 'Activar cliente',
        'enabled_done' => ':name ha sido activado. Todo el personal puede iniciar sesión de nuevo.',
        'disabled_banner' => 'Este negocio está desactivado: nadie puede iniciar sesión.',
        'disabled_detail' => 'Desactivado por :who el :when.',
        'no_users' => 'Todavía no hay usuarios.',

        'usage' => 'Uso',
        'account' => 'Cuenta',
        'business_name' => 'Nombre del negocio',
        'primary_contact' => 'Contacto principal',
        'last_activity' => 'Última actividad',

        'quick_action' => 'Acción rápida',
        'quick_action_placeholder' => 'Seleccionar acción rápida',
        'quick_action_search' => 'Buscar acciones',
        'quick_action_none' => 'Ninguna acción coincide.',
        'copied_url' => 'URL del cliente copiada.',
        'copied_id' => 'ID del cliente copiado.',
        'copy_failed' => 'No se copió nada: el navegador denegó el acceso al portapapeles.',

        'action_groups' => [
            'go' => 'Ir a',
            'copy' => 'Copiar',
            'tell' => 'Enviar',
            'account' => 'Cuenta',
        ],

        'actions' => [
            'open_app' => 'Abrir la aplicación del cliente',
            'view_profile' => 'Ver el perfil del cliente',
            'view_services' => 'Ver servicios',
            'view_team' => 'Ver miembros del equipo',
            'view_email_logs' => 'Ver registros de correo',
            'copy_url' => 'Copiar la URL del cliente',
            'copy_id' => 'Copiar el ID del cliente',
            'send_email' => 'Enviar correo',
            'resend_welcome' => 'Reenviar el correo de bienvenida',
            'send_password_reset' => 'Enviar restablecimiento de contraseña',
            'activate' => 'Activar cliente',
            'deactivate' => 'Desactivar cliente',
        ],

        'reset_sent' => 'Se ha enviado un enlace de restablecimiento a :email.',
        'reset_failed' => 'No se envió ningún enlace. Puede que se haya enviado uno hace muy poco: espera unos minutos e inténtalo de nuevo.',
        'reset_no_owner' => 'Este negocio no tiene propietario, así que no hay a quién enviarle el enlace.',

        'none' => '—',
        'no_owner' => 'Sin propietario',
        'no_plan' => 'Sin plan',
        'trial_days' => ':days días restantes',

        'empty' => 'Todavía no se ha dado de alta ningún negocio.',
        'no_matches' => 'Ningún negocio coincide con esos filtros.',
    ],

    'tabs' => [
        'label' => 'Secciones del cliente',
        'overview' => 'Resumen',
        'services' => 'Servicios',
        'email_log' => 'Registro de correo',
        'sms_log' => 'Registro de SMS',
        'team' => 'Miembros del equipo',
        'subscription' => 'Suscripción',
        'coming_soon' => 'Próximamente',
    ],

    'table' => [
        'search' => 'Buscar',
        'per_page' => 'Filas',
        'no_matches' => 'Nada coincide con esos filtros.',
    ],

    'services' => [
        'search_placeholder' => 'Servicio, descripción o categoría',
        'empty' => 'Este negocio aún no ha añadido servicios.',
        'no_price' => 'Sin precio',
        'all_locations' => 'Todas las ubicaciones',

        'col' => [
            'name' => 'Servicio',
            'category' => 'Categoría',
            'duration' => 'Duración',
            'price' => 'Precio',
            'location' => 'Ubicación',
            'status' => 'Estado',
            'created' => 'Creado',
        ],

        'statuses' => [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ],
    ],

    'emails' => [
        'search_placeholder' => 'Destinatario, asunto o tipo',
        'empty' => 'Este negocio aún no ha enviado ningún correo.',
        'manual' => 'Mensaje manual',
        'automatic' => 'Automático',
        'view_details' => 'Ver detalles',
        'hide_details' => 'Ocultar detalles',

        'col' => [
            'sent' => 'Fecha y hora',
            'recipient' => 'Destinatario',
            'type' => 'Tipo',
            'subject' => 'Asunto',
            'status' => 'Estado',
            'sent_by' => 'Enviado por',
            'provider' => 'Proveedor',
            'action' => 'Acción',
        ],

        'statuses' => [
            'queued' => 'En cola',
            'sent' => 'Enviado',
            'delivered' => 'Entregado',
            'failed' => 'Fallido',
        ],

        'detail' => [
            'sender' => 'De',
            'queued' => 'En cola el',
            'sent' => 'Enviado el',
            'booking' => 'Reserva',
            'failure' => 'Por qué falló',
            'message' => 'Mensaje',
        ],
    ],

    'team' => [
        'search_placeholder' => 'Nombre, correo o puesto',
        'empty' => 'Este negocio aún no ha añadido miembros del equipo.',
        'no_account' => 'Todavía sin cuenta',

        'col' => [
            'name' => 'Nombre',
            'email' => 'Correo',
            'role' => 'Rol',
            'location' => 'Ubicación',
            'status' => 'Estado',
            'last_login' => 'Último acceso',
            'created' => 'Creado',
        ],
    ],

    'sms' => [
        'title' => 'Registro de SMS',
        'intro' => 'Historial de SMS, estado de entrega y actividad de mensajes de este cliente.',
        'items' => [
            'history' => 'Cada mensaje enviado, con su destinatario',
            'delivery' => 'Estado de entrega y de error del proveedor',
            'activity' => 'Qué parte del producto envió cada mensaje',
            'spend' => 'Volumen de mensajes y gasto frente al plan',
        ],
    ],

    'subscription' => [
        'title' => 'Suscripción',
        'intro' => 'Plan, ciclo de facturación y datos de pago de este cliente. El plan y las fechas de prueba ya conocidos están en la pestaña Resumen.',
        'items' => [
            'plan' => 'Plan actual',
            'cycle' => 'Ciclo de facturación',
            'status' => 'Estado de la suscripción',
            'renewal' => 'Fecha de renovación',
            'limits' => 'Límites de uso',
            'method' => 'Método de pago',
            'history' => 'Historial de facturación',
            'change' => 'Mejorar, reducir o cancelar',
        ],
    ],

    'dashboard' => [
        'greeting' => 'Bienvenido, :name',
        'intro' => 'Administración de la plataforma StyleDesk.',
        'recent_activity' => 'Actividad reciente de los administradores',
        'no_activity' => 'Todavía no hay nada registrado.',
    ],

    'profile' => [
        'title' => 'Mi perfil',
        'details' => 'Tus datos',
        'name' => 'Nombre',
        'role' => 'Rol',
        'password' => 'Contraseña',
        'password_hint' => 'Pedimos la contraseña actual porque esta es la pantalla que se usa en un escritorio desatendido.',
        'current_password' => 'Contraseña actual',
        'change_password' => 'Cambiar contraseña',
        'saved' => 'Tus datos se han guardado.',
        'password_saved' => 'Tu contraseña ha cambiado.',
        'wrong_password' => 'Esa no es tu contraseña actual.',
    ],

    'soon' => [
        'title' => 'Llega en la próxima fase',
        'clients' => 'Todos los negocios suscritos a StyleDesk, con su plan, su facturación y su uso.',
        'plans' => 'Los planes de suscripción que vende StyleDesk, con precios, límites y funciones.',
        'billing' => 'Facturas de suscripción, transacciones, pagos fallidos y reembolsos.',
        'settings' => 'Ajustes de la plataforma, administradores y registro de auditoría.',
    ],

    'audit' => [
        'unknown_actor' => 'Desconocido',
        'actions' => [
            'auth_login' => 'Un administrador inició sesión',
            'auth_logout' => 'Un administrador cerró sesión',
            'auth_login_failed' => 'Inicio de sesión fallido',
            'auth_code_sent' => 'Código de verificación enviado',
            'auth_code_requested_unknown' => 'Código pedido por una dirección desconocida',
            'auth_code_verified' => 'Código de verificación aceptado',
            'auth_code_failed' => 'Código de verificación rechazado',
            'auth_password_reset_requested' => 'Restablecimiento de contraseña solicitado',
            'auth_password_reset' => 'Contraseña restablecida',
            'admin_profile_updated' => 'Perfil de administrador actualizado',
            'admin_password_changed' => 'Contraseña de administrador cambiada',
            'client_disabled' => 'Cliente desactivado',
            'client_enabled' => 'Cliente activado',
            'client_password_reset_sent' => 'Restablecimiento de contraseña enviado al propietario del cliente',
        ],
    ],
];
