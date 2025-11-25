<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de reglas disponibles
    |--------------------------------------------------------------------------
    */

    'types' => [
        'days' => [
            'label' => 'Días',
            'fields' => [
                'key' => 'days',
                'operator' => 'in',
                'input' => 'checkboxes',
                'options' => [
                    'monday' => 'Lunes',
                    'tuesday' => 'Martes',
                    'wednesday' => 'Miércoles',
                    'thursday' => 'Jueves',
                    'friday' => 'Viernes',
                    'saturday' => 'Sábado',
                    'sunday' => 'Domingo',
                ],
            ],
        ],

        'limit' => [
            'label' => 'Límite de Ventas',
            'fields' => [
                'key' => 'limit',
                'operator' => '=',
                'input' => 'number',
            ],
        ],

    ],
];
