<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
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
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 85;

    public $restaurant;

    public function mount()
    {
        // Limpiamos cache específica
        Cache::forget("restaurant_data_user_" . Auth::id());

        // 🟢 CORRECCIÓN: Usamos el Tenant actual de Filament
        // Esto asegura que si estoy en el Panel del Restaurante A, solo vea los datos del A.
        $this->restaurant = Filament::getTenant();

        if (!$this->restaurant) {
            abort(403, 'No hay un contexto de restaurante seleccionado.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar Datos')
                // 🟢 Llenamos el formulario con los datos del Tenant actual
                ->mountUsing(fn($form) => $form->fill($this->restaurant->toArray()))
                ->form([
                    TextInput::make('name_comercial')
                        ->label('Nombre Comercial')
                        ->required(),
                    TextInput::make('address')
                        ->label('Dirección')
                        ->required(),
                    FileUpload::make('logo')
                        ->label('Logo del Restaurante')
                        ->image()
                        ->disk('public')
                        // 🟢 Carpeta organizada por el slug del restaurante actual
                        ->directory('tenants/' . $this->restaurant->slug . '/restaurante')
                        ->visibility('public')
                        ->preserveFilenames()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    // Actualizamos el modelo del restaurante actual
                    $this->restaurant->update($data);

                    // Limpieza de cache
                    Cache::forget("restaurant_data_user_" . Auth::id());
                    $this->restaurant->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('Datos actualizados')
                        ->success()
                        ->send();
                }),
        ];
    }
}
