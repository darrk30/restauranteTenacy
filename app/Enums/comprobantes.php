<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Comprobantes: string implements HasColor, HasLabel
{
    case FACTURA = 'FACTURA';
    case BOLETA = 'BOLETA';
    case TICKET = 'TICKET';

    public function getLabel(): string
    {
        return match ($this) {
            self::FACTURA => 'FACTURA',
            self::BOLETA => 'BOLETA',
            self::TICKET => 'TICKET',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FACTURA => 'success',
            self::BOLETA => 'success',
            self::TICKET => 'info',
        };
    }

}
