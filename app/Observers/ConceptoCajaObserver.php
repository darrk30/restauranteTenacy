<?php

namespace App\Observers;

use App\Models\CashRegisterMovement;
use App\Models\ConceptoCaja;
use App\Models\PaymentMethod;
use Filament\Notifications\Notification;

class ConceptoCajaObserver
{
    /**
     * Handle the ConceptoCaja "created" event.
     */
    public function created(ConceptoCaja $concepto): void
    {
        // 1. Buscamos el método de pago "Efectivo" (Asumimos que estos gastos son cash)
        $efectivo = PaymentMethod::where('name', 'like', '%Efectivo%')->first();

        if (!$efectivo) {
            Notification::make()->title('Error: No existe método de pago Efectivo')->danger()->send();
            return;
        }

        // 2. Creamos el Movimiento Financiero Espejo
        CashRegisterMovement::create([
            'session_cash_register_id' => $concepto->session_cash_register_id,
            'payment_method_id'        => $efectivo->id,
            'usuario_id'               => $concepto->usuario_id,
            'tipo'                     => $concepto->tipo_movimiento, // 'ingreso' o 'egreso'
            'motivo'                   => $concepto->motivo,
            'monto'                    => $concepto->monto,
            'observacion'              => null,
            'referencia_type'          => ConceptoCaja::class,
            'referencia_id'            => $concepto->id,
        ]);
    }

    /**
     * Se ejecuta cuando se ACTUALIZA el registro (ej: al dar click en Anular)
     */
    public function updated(ConceptoCaja $concepto): void
    {
        // 1. Verificamos si el estado cambió a 'anulado'
        if ($concepto->isDirty('estado') && $concepto->estado === 'anulado') {

            // 2. Buscamos el movimiento financiero asociado
            $movimientoFinanciero = CashRegisterMovement::where('referencia_type', ConceptoCaja::class)
                ->where('referencia_id', $concepto->id)
                ->first();

            // 3. Si existe, lo eliminamos para revertir el saldo de la caja
            if ($movimientoFinanciero) {
                $movimientoFinanciero->delete();

                // Opcional: Notificación silenciosa o log
            }
        }
    }

    // Helper para no repetir código de descripción
    private function generarDescripcion(ConceptoCaja $concepto): string
    {
        $tipo = $concepto->categoria instanceof \App\Enums\TipoEgreso
            ? $concepto->categoria->getLabel()
            : 'Ingreso';

        $persona = $concepto->personal_id
            ? $concepto->personal->name
            : $concepto->persona_externa;

        return "{$tipo}: {$concepto->motivo} - Ref: {$persona}";
    }

    /**
     * Handle the ConceptoCaja "deleted" event.
     */
    public function deleted(ConceptoCaja $conceptoCaja): void
    {
        //
    }

    /**
     * Handle the ConceptoCaja "restored" event.
     */
    public function restored(ConceptoCaja $conceptoCaja): void
    {
        //
    }

    /**
     * Handle the ConceptoCaja "force deleted" event.
     */
    public function forceDeleted(ConceptoCaja $conceptoCaja): void
    {
        //
    }
}
