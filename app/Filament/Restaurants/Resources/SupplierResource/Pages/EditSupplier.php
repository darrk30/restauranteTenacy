<?php

namespace App\Filament\Restaurants\Resources\SupplierResource\Pages;

use App\Filament\Restaurants\Resources\SupplierResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ver_compras')
                ->label('Historial de Compras')
                ->icon('heroicon-m-shopping-bag')
                ->color('warning')
                ->url(fn () => SupplierResource::getUrl('compras', ['record' => $this->record])),
        ];
    }
}