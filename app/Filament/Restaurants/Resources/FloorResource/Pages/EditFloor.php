<?php

namespace App\Filament\Restaurants\Resources\FloorResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFloor extends EditRecord
{
    protected static string $resource = FloorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
