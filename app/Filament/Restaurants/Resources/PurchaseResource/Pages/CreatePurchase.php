<?php

namespace App\Filament\Restaurants\Resources\PurchaseResource\Pages;

use App\Filament\Restaurants\Resources\PurchaseResource;
use App\Traits\ManjoStockProductos;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    use ManjoStockProductos;

    protected static string $resource = PurchaseResource::class;

    protected function afterCreate(): void
    {
        $this->applyPurchase($this->record);
    }
}
