<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoProducto: string implements HasColor, HasIcon, HasLabel
{
    case Producto = 'Producto';
    case Promocion = 'Promocion';
    case Servicio = 'Servicio';
    case Insumo = 'Insumo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Producto => 'Producto',
            self::Promocion => 'Promoción',
            self::Servicio => 'Servicio',
            self::Insumo => 'Insumo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Producto => 'warning',
            self::Servicio => 'success',
            self::Promocion => 'info',
            self::Insumo => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Producto => 'heroicon-o-cube', // ejemplo ícono para Producto
            self::Servicio => 'heroicon-o-wrench-screwdriver', // ejemplo ícono para Servicio
            self::Insumo => 'heroicon-o-beaker', // ejemplo ícono para Insumo
            self::Promocion => 'heroicon-o-tag', // ejemplo ícono para Promocion
        };
    }
}
