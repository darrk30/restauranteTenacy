<?php

namespace App\Filament\Restaurants\Resources\SaleResource\Pages;

use App\Filament\Restaurants\Resources\SaleResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;
    protected static ?string $title = 'Detalle de Venta';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Reimprimir Ticket')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn($record) => route('sale.ticket.print', $record), shouldOpenInNewTab: true),
        ];
    }
}
