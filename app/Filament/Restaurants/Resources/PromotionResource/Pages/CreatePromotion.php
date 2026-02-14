<?php

namespace App\Filament\Restaurants\Resources\PromotionResource\Pages;

use App\Filament\Restaurants\Resources\PromotionResource;
use App\Models\Promotion;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['name'])) {
            $slug = Str::slug($data['name']. '-' . filament()->getTenant()->id);

            $exists = Promotion::where('slug', $slug)->exists();

            if ($exists) {
                Notification::make()
                    ->title('Slug duplicado')
                    ->body("Ya existe una promoción con el slug: $slug")
                    ->danger()
                    ->send();

                // Detener el guardado sin generar error
                throw new Halt();
            }

            $data['slug'] = $slug;
        }

        return $data;
    }
}
