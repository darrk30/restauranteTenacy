<?php

namespace App\Filament\Restaurants\Resources\PrinterResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\PrinterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrinters extends ListRecords
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
