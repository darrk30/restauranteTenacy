<?php

namespace App\Filament\Restaurants\Resources\PromotionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
