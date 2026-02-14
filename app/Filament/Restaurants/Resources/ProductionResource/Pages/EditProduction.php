<?php

namespace App\Filament\Restaurants\Resources\ProductionResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\ProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Editar ' . $this->record->slug;
    }
}
