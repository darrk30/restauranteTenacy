<?php

namespace App\Filament\Restaurants\Resources\CashRegisterResource\Pages;

use App\Filament\Restaurants\Resources\CashRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCashRegister extends CreateRecord
{
    protected static string $resource = CashRegisterResource::class;
}
