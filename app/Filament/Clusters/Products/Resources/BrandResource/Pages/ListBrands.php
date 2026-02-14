<?php

namespace App\Filament\Clusters\Products\Resources\BrandResource\Pages;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\CreateAction;
use App\Filament\Clusters\Products\Resources\BrandResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords implements HasActions
{
    use InteractsWithActions;
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva')->icon('heroicon-o-plus'),
        ];
    }
}
