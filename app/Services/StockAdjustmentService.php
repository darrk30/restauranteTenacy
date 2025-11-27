<?php

namespace App\Services;

use App\Traits\ManjoStockProductos;

class StockAdjustmentService
{
    use ManjoStockProductos;

    public function revert($adjustment)
    {
        $this->reverseAdjustment($adjustment);
    }
}
