<?php

namespace App\Filament\Restaurants\Resources\SaleResource\Pages;

use App\Filament\Restaurants\Resources\SaleResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Podemos agregar un botón de imprimir aquí en el futuro
            Actions\Action::make('print')
                ->label('Reimprimir')
                ->icon('heroicon-o-printer')
                ->action(fn() => $this->halt()),
        ];
    }
}