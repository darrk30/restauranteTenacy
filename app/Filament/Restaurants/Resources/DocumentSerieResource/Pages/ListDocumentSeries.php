<?php

namespace App\Filament\Restaurants\Resources\DocumentSerieResource\Pages;

use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\DocumentSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSeries extends ListRecords implements HasActions
{
    use InteractsWithActions;
    protected static string $resource = DocumentSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
