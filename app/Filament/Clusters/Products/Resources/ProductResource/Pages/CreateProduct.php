<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

use Exception;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Services\ProductService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use App\Models\Product;
use Filament\Support\Exceptions\Halt;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getState();
        (new ProductService())->handleAfterCreate($this->record, $data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return (new ProductService())->validateAndGenerateSlug($data);
        } catch (Exception $e) {
            Notification::make()
                ->title('El slug ya existe')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt();
        }
    }
}
