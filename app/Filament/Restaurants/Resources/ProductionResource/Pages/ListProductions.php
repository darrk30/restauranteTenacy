<?php

namespace App\Filament\Restaurants\Resources\ProductionResource\Pages;

use App\Filament\Restaurants\Resources\ProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductions extends ListRecords
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
