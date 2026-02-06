<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum TipoEgreso: string implements HasLabel, HasColor
{
    case COMPRAS = 'compras';
    case SERVICIOS = 'servicios';
    case REMUNERACION = 'remuneracion';
    case OTROS = 'otros';

    // Lo que verá el usuario en el Select
    public function getLabel(): ?string
    {
        return match ($this) {
            self::COMPRAS => 'Compras (Mercadería/Insumos)',
            self::SERVICIOS => 'Servicios (Luz, Agua, Internet)',
            self::REMUNERACION => 'Remuneración (Pago Personal)',
            self::OTROS => 'Otros Gastos',
        };
    }

    // Colores para las etiquetas en la tabla (Opcional pero bonito)
    public function getColor(): string | array | null
    {
        return match ($this) {
            self::COMPRAS => 'info',    // Azul
            self::SERVICIOS => 'warning', // Naranja
            self::REMUNERACION => 'success', // Verde
            self::OTROS => 'gray',
        };
    }
}