<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class RestaurantProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Mi Restaurante';
    protected static ?string $title = 'Perfil del Local';
    protected static string $view = 'filament.restaurants.restaurant-profile';

    public $restaurant;

    public function mount()
    {
        Cache::forget("restaurant_data_user_" . Auth::id());

        $user = Auth::user();

        // Buscamos el primer restaurante asociado en la tabla intermedia
        $this->restaurant = $user->restaurants()->first();

        if (!$this->restaurant) {
            abort(403, 'No tienes un restaurante asociado.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar Datos')
                ->mountUsing(fn($form) => $form->fill($this->restaurant->toArray()))
                ->form([
                    TextInput::make('name_comercial')
                        ->label('Nombre Comercial')
                        ->required(),
                    TextInput::make('address')
                        ->label('Dirección')
                        ->required(),
                    FileUpload::make('logo') // Este es el que te faltaba
                        ->label('Logo del Restaurante')
                        ->image() // Solo acepta imágenes
                        ->directory('logos-restaurantes') // Carpeta en storage/app/public
                        ->visibility('public'),
                ])
                ->action(function (array $data) {
                    $this->restaurant->update($data);

                    // Limpiamos cache y refrescamos
                    Cache::forget("restaurant_data_user_" . auth()->id());
                    $this->restaurant->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('Datos actualizados')
                        ->success()
                        ->send();
                }),
        ];
    }
}
