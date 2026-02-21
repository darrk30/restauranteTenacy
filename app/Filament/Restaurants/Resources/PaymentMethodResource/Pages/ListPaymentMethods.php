<?php

namespace App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;

use App\Filament\Restaurants\Resources\PaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
