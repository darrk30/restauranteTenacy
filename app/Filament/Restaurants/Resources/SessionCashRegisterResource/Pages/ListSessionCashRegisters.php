<?php

namespace App\Filament\Restaurants\Resources\SessionCashRegisterResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\SessionCashRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSessionCashRegisters extends ListRecords
{
    protected static string $resource = SessionCashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Aperturar'),
        ];
    }
}
