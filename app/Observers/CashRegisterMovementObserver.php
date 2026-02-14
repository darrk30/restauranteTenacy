<?php

namespace App\Observers;

use App\Models\CashRegisterMovement;
use App\Models\CierreCajaDetalle;

class CashRegisterMovementObserver
{
    /**
     * Se ejecuta al CREAR un movimiento.
     */
    public function created(CashRegisterMovement $movimiento): void
    {
        $this->actualizarResumenCaja($movimiento);
    }

    /**
     * Se ejecuta al EDITAR un movimiento (por si cambian el monto por error).
     */
    public function updated(CashRegisterMovement $movimiento): void
    {
        $this->actualizarResumenCaja($movimiento);
    }

    /**
     * Se ejecuta al ELIMINAR (Anular) un movimiento.
     */
    public function deleted(CashRegisterMovement $movimiento): void
    {
        $this->actualizarResumenCaja($movimiento);
    }

    /**
     * Lógica centralizada para recalcular el saldo.
     * Funciona igual para ingresos y egresos porque vuelve a sumar todo desde cero.
     */
    private function actualizarResumenCaja(CashRegisterMovement $movimiento): void
    {
        // 1. Buscamos o creamos la fila en el cierre de caja
        $detalle = CierreCajaDetalle::firstOrNew([
            'session_cash_register_id' => $movimiento->session_cash_register_id,
            'payment_method_id'        => $movimiento->payment_method_id,
        ]);

        /**
         * 2. RE-CALCULAMOS EL TOTAL ABSOLUTO
         * Consultamos a la BD cuánto suma todo lo que hay ACTUALMENTE.
         * Al ser un evento 'deleted', el registro borrado YA NO saldrá en esta suma,
         * por lo que el ajuste es automático.
         */ 
        $saldoSistema = CashRegisterMovement::where('session_cash_register_id', $movimiento->session_cash_register_id)
            ->where('payment_method_id', $movimiento->payment_method_id)
            ->where('status', 'aprobado')
            ->selectRaw("SUM(
                CASE 
                    WHEN tipo = 'ingreso' THEN monto 
                    WHEN tipo = 'egreso' THEN -monto 
                    ELSE 0 
                END
            ) as total")
            ->value('total');

        // 3. Guardamos el nuevo saldo
        $detalle->monto_sistema = $saldoSistema ?? 0;
        $detalle->save();
    }


    /**
     * Handle the CashRegisterMovement "restored" event.
     */
    public function restored(CashRegisterMovement $cashRegisterMovement): void
    {
        //
    }

    /**
     * Handle the CashRegisterMovement "force deleted" event.
     */
    public function forceDeleted(CashRegisterMovement $cashRegisterMovement): void
    {
        //
    }
}
