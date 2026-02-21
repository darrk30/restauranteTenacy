<?php

namespace App\Filament\Restaurants\Resources\StockAdjustmentResource\Pages;

use App\Filament\Restaurants\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
