<?php

namespace App\Filament\Restaurants\Resources\UnitCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\UnitCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnitCategory extends EditRecord
{
    protected static string $resource = UnitCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
