<?php

namespace App\Enums;

enum DocumentSeriesType: string
{
    case FACTURA = 'Factura';
    case BOLETA = 'Boleta';
    case NOTA_VENTA = 'Nota de venta';
    case NOTA_CREDITO = 'Nota de Crédito';
    case NOTA_DEBITO = 'Nota de Débito';

    // Opcional: Un método para obtener etiquetas amigables
    public function label(): string
    {
        return match($this) {
            self::FACTURA => 'Factura Electrónica',
            self::BOLETA => 'Boleta de Venta',
            self::NOTA_VENTA => 'Nota de Venta',
            self::NOTA_CREDITO => 'Nota de Crédito',
            self::NOTA_DEBITO => 'Nota de Débito',
        };
    }
}