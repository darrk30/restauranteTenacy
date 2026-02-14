<?php

namespace App\Filament\Restaurants\Resources\WarehouseResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
