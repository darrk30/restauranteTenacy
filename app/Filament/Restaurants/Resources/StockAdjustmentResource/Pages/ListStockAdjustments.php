<?php

namespace App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
