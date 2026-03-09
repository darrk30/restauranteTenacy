<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use App\Filament\Restaurants\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

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
                ->visible(fn() => Auth::user()->can('ver_facturas_cliente_rest'))
                ->url(fn() => ClientResource::getUrl('facturas', ['record' => $this->record])),
        ];
    }
}
