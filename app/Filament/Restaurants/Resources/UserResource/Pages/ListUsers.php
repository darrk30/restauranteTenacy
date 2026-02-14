<?php

namespace App\Filament\Restaurants\Resources\UserResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
