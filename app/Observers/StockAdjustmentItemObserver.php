<?php

namespace App\Observers;

use App\Models\StockAdjustmentItem;

class StockAdjustmentItemObserver
{
    /**
     * Handle the StockAdjustmentItem "created" event.
     */
    public function created(StockAdjustmentItem $stockAdjustmentItem): void
    {
        //
    }

    /**
     * Handle the StockAdjustmentItem "updated" event.
     */
    public function updated(StockAdjustmentItem $stockAdjustmentItem): void
    {
        //
    }

    /**
     * Handle the StockAdjustmentItem "deleted" event.
     */
    public function deleted(StockAdjustmentItem $stockAdjustmentItem): void
    {
        //
    }

    /**
     * Handle the StockAdjustmentItem "restored" event.
     */
    public function restored(StockAdjustmentItem $stockAdjustmentItem): void
    {
        //
    }

    /**
     * Handle the StockAdjustmentItem "force deleted" event.
     */
    public function forceDeleted(StockAdjustmentItem $stockAdjustmentItem): void
    {
        //
    }
}
