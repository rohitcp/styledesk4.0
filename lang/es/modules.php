<?php

declare(strict_types=1);

/* La página de Configuración: títulos de grupo y tarjetas de módulo. */

return [
    'groups' => [
        'business_setup' => [
            'name' => 'Configuración del negocio',
            'description' => 'Quién eres, dónde trabajas y cómo presentas el negocio.',
        ],
        'booking_operations' => [
            'name' => 'Reservas y operaciones',
            'description' => 'Las reglas que deciden qué se puede reservar, quién puede hacerlo y cuándo.',
        ],
        'clients_experience' => [
            'name' => 'Clientes y experiencia',
            'description' => 'Lo que tus clientes ven, rellenan y compran.',
        ],
        'communication' => [
            'name' => 'Comunicación',
            'description' => 'Lo que envía StyleDesk, a quién y cómo se lee.',
        ],
        'finance' => [
            'name' => 'Finanzas',
            'description' => 'Cobrar, y todo lo que viene después.',
        ],
        'administration' => [
            'name' => 'Administración',
            'description' => 'Quién puede hacer qué, y qué pasa con tus datos.',
        ],
        'developer_integrations' => [
            'name' => 'Desarrollo e integraciones',
            'description' => 'Conectar StyleDesk con todo lo demás.',
        ],
    ],

    'modules' => [
        'business' => [
            'name' => 'Negocio',
            'description' => 'Nombre del negocio, tipo, datos de contacto y configuración operativa.',
        ],
        'locations' => [
            'name' => 'Ubicaciones',
            'description' => 'Sucursales, direcciones, responsables, horarios de apertura y datos de contacto.',
        ],
        'business-hours' => [
            'name' => 'Horario comercial',
            'description' => 'Horas de apertura y cierre, turnos partidos, festivos y cierres temporales.',
        ],
        'branding' => [
            'name' => 'Identidad de marca',
            'description' => 'Logotipo, favicon y colores de marca en la aplicación, los correos y los recibos.',
        ],
        'languages' => [
            'name' => 'Idiomas',
            'description' => 'Define el idioma principal de la aplicación y elige los idiomas adicionales disponibles para tu equipo.',
        ],
        'currency' => [
            'name' => 'Moneda',
            'description' => 'Tu moneda principal y las monedas secundarias en las que fijas precios.',
        ],
        'booking-rules' => [
            'name' => 'Reglas de reserva',
            'description' => 'Intervalos, plazos de aviso, ventanas de reserva y reglas de las citas.',
        ],
        'services' => [
            'name' => 'Categorías de servicios',
            'description' => 'Las categorías en las que se organiza tu lista de precios, cuáles se ofrecen y en qué orden aparecen.',
        ],
        'resources' => [
            'name' => 'Categorías de recursos',
            'description' => 'Las categorías en las que se agrupan tus elementos reservables —sillones, salas, equipos—, cuáles se ofrecen y en qué orden aparecen.',
        ],
        'staff' => [
            'name' => 'Miembros del personal',
            'description' => 'Miembros del equipo, ubicaciones, horarios, acceso a servicios y situación laboral.',
        ],
        'calendar-scheduling' => [
            'name' => 'Calendario y planificación',
            'description' => 'Comportamiento del calendario, valores predeterminados y cómo se muestran las citas.',
        ],
        'cancellation-no-show' => [
            'name' => 'Cancelaciones y ausencias',
            'description' => 'Políticas y plazos de cancelación, cargos y gestión de ausencias.',
        ],
        'clients' => [
            'name' => 'Clientes',
            'description' => 'Valores predeterminados, preferencias y configuración de las fichas de clientes.',
        ],
        'client-booking' => [
            'name' => 'Reserva del cliente',
            'description' => 'La experiencia de reserva del cliente y lo que puede hacer por su cuenta.',
        ],
        'online-booking' => [
            'name' => 'Reserva online',
            'description' => 'Disponibilidad pública, comportamiento de la página y reglas de la reserva online.',
        ],
        'forms' => [
            'name' => 'Formularios',
            'description' => 'Formularios de admisión, consentimiento y consulta, y cuándo se piden.',
        ],
        'memberships' => [
            'name' => 'Membresía',
            'description' => 'Si el negocio vende membresías, dónde se pueden comprar, qué pasa con los créditos no usados y qué significa cancelar una.',
        ],
        'packages' => [
            'name' => 'Paquetes',
            'description' => 'Servicios agrupados, cómo se venden y cómo se consumen las sesiones.',
        ],
        'gift-cards' => [
            'name' => 'Tarjetas regalo',
            'description' => 'Importes, caducidad, reglas de canje y valores predeterminados.',
        ],
        'loyalty-rewards' => [
            'name' => 'Fidelización y recompensas',
            'description' => 'Puntos, recompensas, reglas de acumulación y cómo se canjean.',
        ],
        'notifications' => [
            'name' => 'Notificaciones',
            'description' => 'Comportamiento de las notificaciones por correo y en la aplicación, y qué eventos avisan a quién.',
        ],
        'email-settings' => [
            'name' => 'Ajustes de correo',
            'description' => 'Nombre y dirección del remitente, responder a, y valores predeterminados del correo.',
        ],
        'email-templates' => [
            'name' => 'Plantillas de correo',
            'description' => 'Correos de confirmación, recordatorio, cancelación, cambio de cita, invitación y bienvenida.',
        ],
        'sms-settings' => [
            'name' => 'Ajustes de SMS',
            'description' => 'Remitente de SMS, mensajes predeterminados y cuándo se envían.',
        ],
        'payments' => [
            'name' => 'Pagos',
            'description' => 'Métodos de pago aceptados, depósitos, comportamiento y valores predeterminados.',
        ],
        'taxes' => [
            'name' => 'Impuestos',
            'description' => 'Tipos impositivos, a qué se aplican y valores predeterminados.',
        ],
        'tips' => [
            'name' => 'Propinas',
            'description' => 'Opciones de propina, porcentajes sugeridos y cómo se reparten.',
        ],
        'receipts-invoices' => [
            'name' => 'Recibos y facturas',
            'description' => 'Numeración, formato y qué aparece en recibos y facturas.',
        ],
        'inventory' => [
            'name' => 'Inventario',
            'description' => 'Valores predeterminados de stock, avisos de stock bajo y ajustes de productos.',
        ],
        'roles-permissions' => [
            'name' => 'Roles y permisos',
            'description' => 'Controla a qué pueden acceder propietarios, administradores, encargados, recepción, prestadores de servicio y roles personalizados.',
        ],
        'security' => [
            'name' => 'Seguridad',
            'description' => 'Comportamiento de la sesión, políticas de acceso y ajustes de seguridad.',
        ],
        'data-privacy' => [
            'name' => 'Datos y privacidad',
            'description' => 'Conservación de datos, consentimiento del cliente y configuración de privacidad.',
        ],
        'import-export' => [
            'name' => 'Importar y exportar',
            'description' => 'Trae datos, llévatelos y ejecuta migraciones.',
        ],
        'system-preferences' => [
            'name' => 'Preferencias del sistema',
            'description' => 'Comportamiento general de StyleDesk y valores predeterminados de la aplicación.',
        ],
        'integrations' => [
            'name' => 'Integraciones',
            'description' => 'Integraciones de terceros y servicios conectados.',
        ],
        'api-webhooks' => [
            'name' => 'API y webhooks',
            'description' => 'Acceso a la API, claves, endpoints de webhook e integraciones para desarrolladores.',
        ],
    ],

    'statuses' => [
        'active' => 'Activo',
        'setup-required' => 'Configuración pendiente',
        'coming-soon' => 'Próximamente',
        'view-only' => 'Solo lectura',
    ],

    'counts' => [
        'active_staff' => '{1} miembro activo|[2,*] miembros activos',
        'pending_invites' => '{1} invitación pendiente|[2,*] invitaciones pendientes',
        'roles' => '{1} rol|[2,*] roles',
        'active_locations' => '{1} ubicación activa|[2,*] ubicaciones activas',
        'upcoming_closures' => '{1} cierre próximo|[2,*] cierres próximos',
        'enabled_currencies' => '{1} moneda|[2,*] monedas',
        'enabled_languages' => '{1} idioma|[2,*] idiomas',
    ],
];
