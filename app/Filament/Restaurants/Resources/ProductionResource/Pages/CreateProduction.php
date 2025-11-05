<?php

namespace App\Filament\Restaurants\Resources\ProductionResource\Pages;

use App\Filament\Restaurants\Resources\ProductionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateProduction extends CreateRecord
{
    protected static string $resource = ProductionResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Crear Área de Producción';
    }
}
