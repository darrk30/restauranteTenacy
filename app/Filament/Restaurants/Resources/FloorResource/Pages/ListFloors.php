<?php

namespace App\Filament\Restaurants\Resources\FloorResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFloors extends ListRecords
{
    protected static string $resource = FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
