<?php

namespace App\Filament\Restaurants\Resources\RoleResource\Pages;

use App\Filament\Restaurants\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_')) {
                unset($data[$key]); // Borra el campo fantasma
            }
        }
        
        return $data;
    }
}
