<?php

namespace App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;

use App\Filament\Restaurants\Resources\PaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;
    protected static ?string $title = 'Editar Método de Pago';

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }
}
