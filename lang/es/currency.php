<?php

declare(strict_types=1);

/* El módulo de Moneda. Los nombres de las monedas viven en su configuración. */

return [
    'title' => 'Moneda',
    'intro' => 'La moneda en la que fijas tus precios y las monedas adicionales en las que también los fijas.',
    'edit' => 'Editar monedas',
    'saved' => 'Configuración de moneda actualizada correctamente.',

    'primary' => 'Moneda principal',

    'is_primary' => 'principal',
    'primary_hint' => 'El valor predeterminado para servicios, productos, paquetes, membresías, depósitos, cargos, descuentos, impuestos, pagos, reembolsos e informes.',
    'secondary' => 'Monedas adicionales',
    'secondary_hint' => 'Otras monedas en las que fijas precios. La principal siempre está disponible y no aparece aquí.',
    'enabled' => 'Monedas activas',
    'format' => 'Cómo se muestran los precios',
    'format_hint' => 'Lo define la moneda, no tú: el símbolo, su posición, los separadores y el número de decimales.',

    'single_currency' => 'Fijas precios en una sola moneda. Añade otra y los campos de precio pedirán un importe en cada una.',

    'no_conversion' => 'Los precios no se convierten entre monedas. Tú fijas el precio de cada una, así que una variación del cambio de un día para otro nunca modifica lo que se le presupuestó a un cliente.',
    'scope_note' => 'Cambiar la moneda principal no vuelve a fijar ningún precio. Los precios existentes conservan la moneda en la que se introdujeron.',

    'validation' => [
        'primary_required' => 'Elige una moneda principal.',
        'unsupported' => 'Esa moneda no está disponible.',
    ],
];
