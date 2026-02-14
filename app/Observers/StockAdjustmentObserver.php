<?php

namespace App\Observers;

use App\Models\StockAdjustment;
use Illuminate\Support\Facades\Auth;

class StockAdjustmentObserver
{

    public function creating(StockAdjustment $adjustment): void
    {
        // Generar código automático
        $lastId = StockAdjustment::max('id') + 1;
        $adjustment->codigo = 'AJ-' . str_pad($lastId, 6, '0', STR_PAD_LEFT);
        $adjustment->user_id = Auth::id();

    }

    /**
     * Handle the StockAdjustment "created" event.
     */
    public function created(StockAdjustment $stockAdjustment): void
    {
        //
    }

    /**
     * Handle the StockAdjustment "updated" event.
     */
    public function updated(StockAdjustment $stockAdjustment): void
    {
        //
    }

    /**
     * Handle the StockAdjustment "deleted" event.
     */
    public function deleted(StockAdjustment $stockAdjustment): void
    {
        //
    }

    /**
     * Handle the StockAdjustment "restored" event.
     */
    public function restored(StockAdjustment $stockAdjustment): void
    {
        //
    }

    /**
     * Handle the StockAdjustment "force deleted" event.
     */
    public function forceDeleted(StockAdjustment $stockAdjustment): void
    {
        //
    }
}
