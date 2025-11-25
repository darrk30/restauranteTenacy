<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TIPOS DE REGLAS DE PROMOCIÓN
    |--------------------------------------------------------------------------
    |
    | Cada regla define:
    |   - label: nombre visible en el select
    |   - fields:
    |         key: columna lógica usada por tu sistema para evaluar la regla
    |         operator: operador de comparación (=, in, between, >=, etc)
    |         input: tipo de input del formulario (checkboxes, number, text, date, time_range, etc)
    |         options: si aplica, lista de opciones
    |
    */

    'types' => [

        /*
        |--------------------------------------------------------------------------
        | 1. DÍAS DE LA SEMANA
        |--------------------------------------------------------------------------
        | Aplica la promoción solo ciertos días.
        */
        'days' => [
            'label' => 'Días',
            'fields' => [
                'key' => 'days',
                'operator' => 'in',
                'input' => 'checkboxes',
                'options' => [
                    'monday'    => 'Lunes',
                    'tuesday'   => 'Martes',
                    'wednesday' => 'Miércoles',
                    'thursday'  => 'Jueves',
                    'friday'    => 'Viernes',
                    'saturday'  => 'Sábado',
                    'sunday'    => 'Domingo',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 2. LÍMITE DE VENTAS
        |--------------------------------------------------------------------------
        | Cuántas veces la promoción puede ser utilizada.
        */
        'limit' => [
            'label' => 'Límite de Ventas',
            'fields' => [
                'key' => 'limit',
                'operator' => '=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 3. Monto mínimo de compra
        |--------------------------------------------------------------------------
        */
        'min_amount' => [
            'label' => 'Monto mínimo',
            'fields' => [
                'key' => 'amount',
                'operator' => '>=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 4. Monto máximo
        |--------------------------------------------------------------------------
        */
        'max_amount' => [
            'label' => 'Monto máximo',
            'fields' => [
                'key' => 'amount',
                'operator' => '<=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 5. Cantidad mínima de productos
        |--------------------------------------------------------------------------
        */
        'min_qty' => [
            'label' => 'Cantidad mínima de productos',
            'fields' => [
                'key' => 'quantity',
                'operator' => '>=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 6. Métodos de pago permitidos
        |--------------------------------------------------------------------------
        */
        'payment_method' => [
            'label' => 'Método de Pago',
            'fields' => [
                'key' => 'payment_method',
                'operator' => 'in',
                'input' => 'checkboxes',
                'options' => [
                    'cash' => 'Efectivo',
                    'yape' => 'Yape',
                    'card' => 'Tarjeta',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 7. Cliente nuevo (true/false)
        |--------------------------------------------------------------------------
        */
        'new_customer' => [
            'label' => 'Cliente Nuevo',
            'fields' => [
                'key' => 'is_new_customer',
                'operator' => '=',
                'input' => 'yes_no',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 8. Método de servicio (Delivery / Local)
        |--------------------------------------------------------------------------
        */
        'service_type' => [
            'label' => 'Tipo de Servicio',
            'fields' => [
                'key' => 'service_type',
                'operator' => '=',
                'input' => 'select',
                'options' => [
                    'local'   => 'En local',
                    'delivery'=> 'Delivery',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 9. Rango de horas
        |--------------------------------------------------------------------------
        | Sí, incluye 2 valores: start_time y end_time
        */
        'hour_range' => [
            'label' => 'Rango de Horas',
            'fields' => [
                'key' => 'hour',
                'operator' => 'between',
                'input' => 'time_range', // manejarás en un custom form
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 10. Días específicos (fecha exacta)
        |--------------------------------------------------------------------------
        */
        'specific_date' => [
            'label' => 'Fecha específica',
            'fields' => [
                'key' => 'date',
                'operator' => '=',
                'input' => 'date',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 11. Categorías permitidas
        |--------------------------------------------------------------------------
        */
        'category_included' => [
            'label' => 'Categorías permitidas',
            'fields' => [
                'key' => 'category_id',
                'operator' => 'in',
                'input' => 'select_multiple_dynamic', // Ej: Select::make(...)
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 12. Productos permitidos
        |--------------------------------------------------------------------------
        */
        'product_included' => [
            'label' => 'Productos permitidos',
            'fields' => [
                'key' => 'product_id',
                'operator' => 'in',
                'input' => 'select_multiple_dynamic',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 13. Límite por cliente
        |--------------------------------------------------------------------------
        */
        'limit_per_customer' => [
            'label' => 'Límite por cliente',
            'fields' => [
                'key' => 'limit_per_customer',
                'operator' => '=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 14. Límite por día
        |--------------------------------------------------------------------------
        */
        'limit_per_day' => [
            'label' => 'Límite por día',
            'fields' => [
                'key' => 'limit_per_day',
                'operator' => '=',
                'input' => 'number',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 15. Zona de delivery
        |--------------------------------------------------------------------------
        */
        'delivery_zone' => [
            'label' => 'Zona de Delivery',
            'fields' => [
                'key' => 'zone',
                'operator' => 'in',
                'input' => 'select_multiple_dynamic',
            ],
        ],

    ],

];
