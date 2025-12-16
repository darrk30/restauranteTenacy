<?php

namespace App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;

use App\Filament\Restaurants\Resources\StockAdjustmentResource;
use App\Models\Unit;
use App\Models\WarehouseStock;
use App\Traits\ManjoStockProductos;
use Filament\Resources\Pages\CreateRecord;

class CreateStockAdjustment extends CreateRecord
{
    use ManjoStockProductos;
    protected static string $resource = StockAdjustmentResource::class;

    protected function afterCreate(): void
    {
        $this->applyAdjustment($this->record);
    }
}
