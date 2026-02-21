<?php

namespace App\Filament\Restaurants\Resources\UnitCategoryResource\Pages;

use App\Filament\Restaurants\Resources\UnitCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnitCategories extends ListRecords
{
    protected static string $resource = UnitCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
