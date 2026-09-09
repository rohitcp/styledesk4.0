<?php

declare(strict_types=1);

return [

    'title' => 'Propinas',
    'intro' => 'Si se pide propina a los clientes, qué se les ofrece y a qué servicios se aplica.',
    'back' => 'Configuración',

    'enable' => 'Aceptar propinas',
    'enable_hint' => 'Añade un paso de propina al cobrar. Desactivarlo oculta la pregunta y conserva todo lo de abajo tal como está.',
    'disabled_note' => 'Las propinas están desactivadas. Lo configurado aquí se conserva y vuelve en cuanto las actives.',

    /* Two tabs once tipping is on: what to suggest, and which services it
       applies to. */
    'tabs' => [
        'suggest' => 'Qué sugerir',
        'services' => 'Servicios',
    ],

    'defaults' => 'Qué sugerir',
    'defaults_hint' => 'Lo que usa un servicio cuando no dice otra cosa.',
    'tip_type' => 'Tipo de propina',
    'types' => [
        'percent' => 'Porcentaje',
        'fixed' => 'Importe fijo',
    ],
    'default_tip' => 'Propina predeterminada',
    'default_tip_hint' => 'Un porcentaje de la parte de la cuenta con propina, o una cantidad fija.',

    'percentages' => 'Opciones al cobrar',
    'percentages_hint' => 'Hasta seis. El cliente también dispone de una casilla para escribir la suya.',

    'require_selection' => 'Pedir al cliente que elija',
    'require_selection_hint' => 'Debe responder antes de completar el pago. Responder «Sin propina» también cuenta: es una pregunta, no un cargo.',
    'allow_no_tip' => 'Ofrecer «Sin propina»',
    'allow_no_tip_hint' => 'Recomendado. Sin esto el cliente no puede rechazarla.',

    'services' => 'Servicios',
    'services_hint' => 'Qué servicios llevan propina y qué sugiere cada uno. En blanco sigue la configuración de arriba.',
    'columns' => [
        'service' => 'Servicio',
        'category' => 'Categoría',
        'price' => 'Precio',
        'tips' => 'Propinas',
        'default' => 'Propina predeterminada',
        'type' => 'Tipo',
        'required' => 'Debe elegir',
        'no_tip' => 'Permite «Sin propina»',
        'status' => 'Estado',
    ],
    'follows_default' => 'Sigue el valor predeterminado',
    'accepted' => 'Aceptadas',
    'not_accepted' => 'Sin propina',
    'no_services' => 'Todavía no hay servicios activos que configurar.',

    'edit_service' => 'Configuración de propinas',
    'edit_service_for' => 'Propinas — :name',
    'service_card_hint' => 'Cómo funcionan las propinas en este servicio. Parte de los valores de Ajustes → Propinas y puede cambiarse aquí sin modificarlos.',
    'accepts' => 'Aceptar propinas para este servicio',
    'accepts_hint' => 'Desactívalo para lo que nadie trabaja, como los productos.',

    'panel' => [
        'title' => 'Propina',
        'eligible' => 'Propina sobre',
        'custom' => 'Otra cantidad',
        'none' => 'Sin propina',
        'selected' => 'Propina',
        'required' => 'Elige una opción de propina antes de cobrar.',
        'not_eligible' => 'Nada de esta reserva lleva propina.',
    ],

    'saved' => 'Configuración de propinas guardada.',
    'enabled' => 'Las propinas están activadas.',
    'disabled' => 'Las propinas están desactivadas. Se conserva tu configuración.',
    'service_saved' => 'Configuración de propinas del servicio guardada.',
];
