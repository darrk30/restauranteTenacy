<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use App\Filament\Restaurants\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
            \Filament\Actions\Action::make('ver_facturas')
                ->label('Ver Facturas del Cliente')
                ->icon('heroicon-m-document-magnifying-glass')
                ->color('info')
                ->url(fn() => ClientResource::getUrl('facturas', ['record' => $this->record])),
        ];
    }
}
