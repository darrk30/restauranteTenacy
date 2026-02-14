<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum statusPedido: string implements HasColor, HasIcon, HasLabel
{
    case Pendiente = 'pendiente';
    case Servido = 'servido';
    case Pagado = 'pagado';
    case Cancelado = 'cancelado';
    case Archivado = 'archivado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Servido => 'Servido',
            self::Pagado => 'Pagado',
            self::Cancelado => 'Cancelado',
            self::Archivado => 'Archivado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Servido => 'success',
            self::Pagado => 'primary',
            self::Cancelado => 'danger',
            self::Archivado => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pendiente => 'heroicon-o-clock',            // pendiente
            self::Servido => 'heroicon-o-check-circle',       // servido
            self::Pagado => 'heroicon-o-currency-dollar',     // pagado
            self::Cancelado => 'heroicon-o-x-circle',         // cancelado
            self::Archivado => 'heroicon-o-archive-box-x-mark', // archivado

        };
    }
}
