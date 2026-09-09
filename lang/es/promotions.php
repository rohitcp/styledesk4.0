<?php

declare(strict_types=1);

return [

    'title' => 'Cupones y ofertas',
    'intro' => 'Descuentos que el cliente escribe, y otros que se aplican solos.',
    'new' => 'Crear cupón u oferta',
    'none_yet' => 'Todavía no hay cupones ni ofertas.',
    'none_yet_hint' => 'Un descuento de primera visita es por donde suelen empezar.',
    'automatic' => 'Automática',
    'no_expiry_short' => 'Sin caducidad',
    'copy_of' => ':name (copia)',

    'summary' => [
        'active' => 'Ofertas activas',
        'scheduled' => 'Programadas',
        'expired' => 'Caducada',
        'redemptions' => 'Canjes totales',
    ],

    'columns' => [
        'name' => 'Nombre',
        'code' => 'Código',
        'type' => 'Tipo',
        'discount' => 'Descuento',
        'applies' => 'Se aplica a',
        'starts' => 'Inicio',
        'ends' => 'Fin',
        'used' => 'Usos',
        'status' => 'Estado',
    ],

    'types' => [
        'coupon' => 'Cupón',
        'offer' => 'Oferta',
    ],

    'statuses' => [
        'draft' => 'Borrador',
        'scheduled' => 'Programadas',
        'active' => 'Activa',
        'expired' => 'Caducada',
        'disabled' => 'Desactivada',
    ],

    'applies' => [
        'booking' => 'Toda la reserva',
        'all_services' => 'Todos los servicios',
        'services' => 'Servicios seleccionados',
        'categories' => 'Categorías seleccionadas',
    ],

    'filters' => [
        'all_statuses' => 'Todos los estados',
        'all_types' => 'Todos los tipos',
        'all_locations' => 'Todas las ubicaciones',
        'reset' => 'Restablecer',
    ],

    'search' => 'Busca por nombre, código o servicio…',
    'results' => [
        'zero' => 'Ningún cupón u oferta coincide',
        'one' => '1 cupón u oferta',
        'many' => ':count cupones y ofertas',
        'clear' => 'Quitar filtros',
    ],
    'showing' => 'Mostrando :from–:to de :total',
    'empty' => 'Nada coincide con esos filtros.',
    'actions_for' => 'Acciones para :name',

    'form' => [
        'create_title' => 'Crear cupón u oferta',
        'edit_title' => 'Editar cupón u oferta',

        'templates' => 'Empezar con una plantilla',
        'templates_hint' => 'Una plantilla solo rellena el formulario. Todo lo que pone se puede cambiar antes de guardar.',
        'scratch' => 'Empezar desde cero',

        'basics' => 'Información básica',
        'name' => 'Promotion name',
        'name_placeholder' => '20% para clientes nuevos',
        'description' => 'Descripción interna',
        'description_hint' => 'Para el equipo, no para el cliente.',
        'type' => 'Tipo de promoción',
        'type_coupon' => 'Código de cupón',
        'type_coupon_hint' => 'Lo escribe el cliente o la recepción.',
        'type_offer' => 'Oferta automática',
        'type_offer_hint' => 'Se aplica sola a cualquier reserva que cumpla.',
        'code' => 'Código de cupón',
        'code_hint' => 'Letras, números y guiones. Se guarda en mayúsculas.',
        'generate' => 'Generar',

        'discount' => 'Descuento',
        'discount_type' => 'Tipo de descuento',
        'percent' => 'Porcentaje',
        'fixed' => 'Importe fijo',
        'amount' => 'Importe',
        'percent_hint' => 'Un porcentaje de la parte a la que se aplica. Hasta 100.',
        'fixed_hint' => 'Una cantidad fija de descuento, nunca mayor que la parte a la que se aplica.',

        'applies' => 'Se aplica a',
        'applies_hint' => 'El porcentaje se aplica sobre la parte a la que corresponde, no sobre toda la cuenta.',
        'services' => 'Servicios',
        'categories' => 'Categorías',

        'locations' => 'Ubicaciones',
        'all_locations' => 'Todas las ubicaciones',
        'selected_locations' => 'Ubicaciones seleccionadas',

        'validity' => 'Vigencia',
        'starts' => 'Fecha de inicio',
        'ends' => 'Fecha de fin',
        'no_expiry' => 'Sin fecha de caducidad',
        'days' => 'Días válidos',
        'days_hint' => 'Déjalos todos marcados salvo que la promoción sea para días concretos: un martes vacío no se puede vender el miércoles.',

        'eligibility' => 'Para quién es',
        'eligibility_all' => 'Todos los clientes',
        'eligibility_new' => 'Solo clientes nuevos',
        'eligibility_new_hint' => 'Todavía no se les ha completado ninguna cita.',
        'eligibility_existing' => 'Solo clientes existentes',
        'eligibility_selected' => 'Clientes seleccionados',
        'clients' => 'Clientes',

        'redemption' => 'Reglas de canje',
        'min_spend' => 'Importe mínimo de la reserva',
        'min_spend_hint' => 'Opcional. Evita usar un descuento fijo en una reserva muy pequeña.',
        'total_limit' => 'Canjes totales',
        'total_limit_hint' => 'Déjalo en blanco para ilimitado.',
        'per_client_limit' => 'Por cliente',
        'per_client_limit_hint' => 'En blanco para ilimitado. Una vez por cliente es lo habitual.',

        'availability' => 'Dónde se puede usar',
        'allow_online' => 'Permitir que los clientes lo usen en la reserva online',
        'combinable' => 'Se puede combinar con otra promoción',
        'combinable_hint' => 'Desactivado es lo seguro: una promoción por reserva.',
        'draft' => 'Guardar como borrador',
        'draft_hint' => 'Un borrador no se ofrece a nadie hasta que lo desactives.',

        'save' => 'Guardar',
        'cancel' => 'Cancelar',
    ],

    'weekdays' => [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ],

    'details' => [
        'title' => 'Detalles de la oferta',
        'discount' => 'Descuento',
        'for' => 'Disponible para',
        'services' => 'Servicios',
        'locations' => 'Ubicaciones',
        'valid' => 'Vigencia',
        'usage' => 'Uso',
        'online' => 'Reserva online',
        'online_yes' => 'Los clientes pueden usarlo por su cuenta',
        'online_no' => 'Solo el equipo',
        'created_by' => 'Creado por :name',
        'no_expiry' => 'Sin caducidad',
        'every_day' => 'Todos los días',
    ],

    'report' => [
        'title' => 'Cómo va',
        'redemptions' => 'Canjes',
        'clients' => 'Clientes',
        'discount' => 'Descuento aplicado',
        'revenue' => 'Ingresos de esas reservas',
        'revenue_hint' => 'Lo que sumaron esas reservas; no afirma que la promoción las causara.',
        'none' => 'Todavía no lo ha usado nadie.',
    ],

    'actions' => [
        'view' => 'Ver',
        'edit' => 'Editar',
        'duplicate' => 'Duplicar',
        'disable' => 'Desactivar',
        'enable' => 'Activar',
        'back' => 'Cupones y ofertas',
    ],

    'refused' => [
        'not_running' => 'Esa promoción no está activa.',
        'not_started' => 'Esa promoción aún no ha empezado.',
        'expired' => 'Esa promoción ha caducado.',
        'wrong_day' => 'Esa promoción no se aplica este día.',
        'wrong_location' => 'Esa promoción no está disponible en esta ubicación.',
        'needs_a_client' => 'Esa promoción es solo para ciertos clientes, así que la reserva necesita uno.',
        'new_only' => 'Esa promoción es solo para clientes nuevos.',
        'existing_only' => 'Esa promoción es solo para clientes existentes.',
        'not_for_this_client' => 'Esa promoción no está disponible para este cliente.',
        'no_eligible_services' => 'Nada de esta reserva cumple con esa promoción.',
        'under_minimum' => 'Esa promoción requiere una reserva de al menos :amount.',
        'fully_redeemed' => 'Esa promoción ya se ha canjeado por completo.',
        'client_limit' => 'Este cliente ya ha usado esa promoción.',
        'unknown_code' => 'No hay ninguna promoción con ese código.',
    ],

    'created' => 'Cupón u oferta creada.',
    'saved' => 'Cupón u oferta guardada.',
    'duplicated' => 'Copiada. Se guarda como borrador.',
    'disabled' => 'Promoción desactivada.',
    'enabled' => 'Promoción activada.',
];
