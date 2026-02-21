<?php

namespace App\Filament\Restaurants\Resources\UserResource\Pages;

use App\Filament\Restaurants\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
