<?php

namespace App\Filament\Restaurants\Resources\DocumentSerieResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\DocumentSerieResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentSerie extends EditRecord
{
    protected static string $resource = DocumentSerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
