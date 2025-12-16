<?php

namespace App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;

use App\Filament\Restaurants\Resources\PaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;
}
