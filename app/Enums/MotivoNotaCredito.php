<?php

namespace App\Enums;

enum MotivoNotaCredito: string
{
    case ANULACION_OPERACION = '01';
    case ANULACION_ERROR_RUC = '02';
    case CORRECCION_ERROR_DESCRIPCION = '03';
    case DESCUENTO_GLOBAL = '04';
    case DESCUENTO_POR_ITEM = '05';
    case DEVOLUCION_TOTAL = '06';
    case DEVOLUCION_POR_ITEM = '07';
    case BONIFICACION = '08';
    case DISMINUCION_VALOR = '09';

    /**
     * Devuelve la descripción oficial de SUNAT para el catálogo 09.
     */
    public function descripcion(): string
    {
        return match($this) {
            self::ANULACION_OPERACION => 'Anulación de la operación',
            self::ANULACION_ERROR_RUC => 'Anulación por error en el RUC',
            self::CORRECCION_ERROR_DESCRIPCION => 'Corrección por error en la descripción',
            self::DESCUENTO_GLOBAL => 'Descuento global',
            self::DESCUENTO_POR_ITEM => 'Descuento por ítem',
            self::DEVOLUCION_TOTAL => 'Devolución total',
            self::DEVOLUCION_POR_ITEM => 'Devolución por ítem',
            self::BONIFICACION => 'Bonificación',
            self::DISMINUCION_VALOR => 'Disminución en el valor',
        };
    }

    /**
     * Retorna un array con clave-valor, ideal para Selects en Filament o Livewire.
     */
    public static function opcionesParaSelect(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->descripcion();
        }
        return $opciones;
    }
}