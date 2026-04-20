<?php

namespace App\Enums;

class MotivosSUNAT
{
    /**
     * CATÁLOGO 09: Motivos para Notas de Crédito
     * Se usa cuando el tipo de nota es '07'
     */
    public static function opcionesCredito(): array
    {
        return [
            '01' => 'Anulación de la operación',
            '02' => 'Anulación por error en el RUC',
            '03' => 'Corrección por error en la descripción',
            '04' => 'Descuento global',
            '05' => 'Descuento por ítem',
            '06' => 'Devolución total',
            '07' => 'Devolución por ítem',
            '08' => 'Bonificación',
            '09' => 'Disminución en el valor',
        ];
    }

    /**
     * CATÁLOGO 10: Motivos para Notas de Débito
     * Se usa cuando el tipo de nota es '08'
     */
    public static function opcionesDebito(): array
    {
        return [
            '01' => 'Intereses por mora',
            '02' => 'Aumento en el valor',
            '03' => 'Penalidades/ otros conceptos',
        ];
    }

    /**
     * Método auxiliar opcional: 
     * Devuelve la descripción en texto si le pasas el código y el tipo de comprobante.
     * Útil si necesitas imprimir el nombre del motivo en tu PDF o ticket.
     */
    public static function obtenerDescripcion(string $codigo, string $tipoNota): string
    {
        if ($tipoNota === '08') {
            $opciones = self::opcionesDebito();
        } else {
            $opciones = self::opcionesCredito();
        }

        return $opciones[$codigo] ?? 'Motivo desconocido';
    }
}
