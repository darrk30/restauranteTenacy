<?php

namespace App\Filament\Restaurants\Resources\SaleResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
