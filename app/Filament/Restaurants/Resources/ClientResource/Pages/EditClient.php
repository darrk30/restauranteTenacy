<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
