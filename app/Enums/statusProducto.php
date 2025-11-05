<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusProducto: string implements HasColor, HasIcon, HasLabel
{
    case Activo = 'Activo';
    case Inactivo = 'Inactivo';
    case Archivado = 'Archivado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
            self::Archivado => 'Archivado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Inactivo => 'danger',
            self::Archivado => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Activo => 'heroicon-o-check-circle',         // activo
            self::Inactivo => 'heroicon-o-x-circle',           // inactivo
            self::Archivado => 'heroicon-o-archive-box-x-mark', // archivado

        };
    }
}
