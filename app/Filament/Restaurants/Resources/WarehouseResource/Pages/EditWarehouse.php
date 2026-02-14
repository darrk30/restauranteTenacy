<?php

namespace App\Filament\Restaurants\Resources\WarehouseResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
