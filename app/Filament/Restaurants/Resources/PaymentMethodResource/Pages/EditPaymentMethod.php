<?php

namespace App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\PaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
