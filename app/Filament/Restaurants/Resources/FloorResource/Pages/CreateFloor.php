<?php

namespace App\Filament\Restaurants\Resources\FloorResource\Pages;

use App\Filament\Restaurants\Resources\FloorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFloor extends CreateRecord
{
    protected static string $resource = FloorResource::class;
    protected static ?string $title = 'Nuevo Piso';
}
