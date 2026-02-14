<?php

namespace App\Filament\Restaurants\Resources\UnitCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\UnitCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnitCategories extends ListRecords
{
    protected static string $resource = UnitCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
