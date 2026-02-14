<?php

namespace App\Filament\Restaurants\Resources\DocumentSerieResource\Pages;

use App\Filament\Restaurants\Resources\DocumentSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSeries extends ListRecords
{
    protected static string $resource = DocumentSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
