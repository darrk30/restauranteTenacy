<?php

namespace App\Filament\Restaurants\Resources\SessionCashRegisterResource\Pages;

use App\Filament\Restaurants\Resources\SessionCashRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSessionCashRegister extends EditRecord
{
    protected static string $resource = SessionCashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Forzamos el cambio de estado y fecha al guardar
        $data['status'] = 'closed';
        $data['closed_at'] = now();
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Al terminar, volver a la lista
        return $this->getResource()::getUrl('index');
    }
}
