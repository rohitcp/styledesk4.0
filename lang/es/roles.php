<?php

declare(strict_types=1);

/* Los roles del sistema. Los roles personalizados conservan su propio nombre. */

return [
    'owner' => [
        'name' => 'Propietario',
        'description' => 'Acceso completo al negocio, el personal, los ajustes, la facturación y los datos operativos.',
    ],
    'administrator' => [
        'name' => 'Administrador',
        'description' => 'Acceso operativo y administrativo completo, salvo las acciones reservadas al propietario.',
    ],
    'manager' => [
        'name' => 'Encargado',
        'description' => 'Operativa diaria, personal, servicios, clientes e informes de sus ubicaciones.',
    ],
    'front-desk' => [
        'name' => 'Recepción',
        'description' => 'Citas, clientes, reservas, entradas y salidas, y actividades de recepción.',
    ],
    'service-provider' => [
        'name' => 'Prestador de servicios',
        'description' => 'Su propio calendario, sus citas y los clientes y servicios que tenga asignados.',
    ],
];
