<?php

namespace App\Filament\Restaurants\Resources\ConceptoCajasResource\Pages;

use App\Filament\Restaurants\Resources\ConceptoCajasResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConceptoCajas extends EditRecord
{
    protected static string $resource = ConceptoCajasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }
}
