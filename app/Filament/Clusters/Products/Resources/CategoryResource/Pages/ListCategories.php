<?php

namespace App\Filament\Clusters\Products\Resources\CategoryResource\Pages;


use App\Filament\Clusters\Products\Resources\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
