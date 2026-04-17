<?php

namespace App\Filament\Restaurants\Pages;

use App\Services\BillingSyncService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;

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
        Cache::forget("restaurant_data_user_" . Auth::id());
        $this->restaurant = Filament::getTenant();

        if (!$this->restaurant) {
            abort(403, 'No hay un contexto de restaurante seleccionado.');
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()->canAny(['editar_mi_restaurante_rest']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar Datos del Local')
                ->icon('heroicon-m-pencil-square')
                ->modalWidth('4xl')
                ->mountUsing(fn($form) => $form->fill($this->restaurant->toArray()))
                ->form([
                    Section::make('Información de Identidad')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')->label('Razón Social')->required(),
                                TextInput::make('name_comercial')->label('Nombre Comercial')->required(),
                                TextInput::make('ruc')->label('RUC')->numeric()->length(11)->required(),
                            ]),
                        ]),

                    Section::make('Contacto y Ubicación')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('email')->label('Correo')->email(),
                                TextInput::make('phone')->label('Teléfono'),
                                TextInput::make('address')->label('Dirección')->columnSpanFull()->required(),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('department')->label('Departamento'),
                                TextInput::make('province')->label('Provincia'),
                                TextInput::make('district')->label('Distrito'),
                                TextInput::make('ubigeo')->label('Ubigeo (SUNAT)')->numeric()->length(6),
                            ]),
                        ]),

                    Section::make('Identidad Visual')
                        ->schema([
                            FileUpload::make('logo')
                                ->label('Logo')
                                ->image()
                                ->disk('public')
                                ->directory('tenants/' . $this->restaurant->slug . '/logo'),
                        ]),
                ])
                ->action(function (array $data) {
                    $this->restaurant->update($data);
                    $sincronizado = BillingSyncService::sync($this->restaurant);
                    if ($sincronizado) {
                        Notification::make()->title('Perfil y Facturador actualizados')->success()->send();
                    } else {
                        Notification::make()->title('Perfil guardado localmente (Error API)')->warning()->send();
                    }
                    $this->restaurant->refresh();
                })
        ];
    }
}
