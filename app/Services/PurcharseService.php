<?php

namespace App\Services;

use App\Traits\ManjoStockProductos;

class PurcharseService
{
    use ManjoStockProductos;

    public function revert($purchase)
    {
        $this->reversePurchase($purchase);
    }

}
