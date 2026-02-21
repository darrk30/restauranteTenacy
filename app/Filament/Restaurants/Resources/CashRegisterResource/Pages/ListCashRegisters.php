<?php

namespace App\Filament\Restaurants\Resources\CashRegisterResource\Pages;

use App\Filament\Restaurants\Resources\CashRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashRegisters extends ListRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Nueva Caja') 
                ->modalSubmitActionLabel('Crear Caja'), 
        ];
    }
}