<?php

namespace App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
