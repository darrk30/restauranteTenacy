<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
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
        Cache::forget("restaurant_data_user_" . Auth::id());
        $this->restaurant = Filament::getTenant();

        if (!$this->restaurant) {
            abort(403, 'No hay un contexto de restaurante seleccionado.');
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()->canAny([
            'editar_mi_restaurante_rest',
        ]);
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
                        ->description('Datos principales y fiscales del establecimiento.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->label('Razón Social')
                                    ->required(),
                                TextInput::make('name_comercial')
                                    ->label('Nombre Comercial')
                                    ->required(),
                                TextInput::make('ruc')
                                    ->label('RUC')
                                    ->numeric()
                                    ->length(11)
                                    ->required(),
                            ]),
                        ]),

                    // --- NUEVA SECCIÓN: CONFIGURACIÓN SUNAT ---
                    Section::make('Configuración de Facturación Electrónica (SUNAT)')
                        ->description('Credenciales necesarias para emitir comprobantes legales.')
                        ->collapsible() // Para que no ocupe tanto espacio si no se edita
                        ->schema([
                            Grid::make(2)->schema([
                                Toggle::make('production')
                                    ->label('Modo Producción')
                                    ->helperText('Activar solo si ya vas a emitir boletas/facturas reales.')
                                    ->onColor('success')
                                    ->offColor('danger'),

                                FileUpload::make('cert_path')
                                    ->label('Certificado Digital (PEM / TXT)')
                                    ->helperText('Sube el archivo .pem o .txt que contiene tu certificado.')
                                    ->disk('public')
                                    ->directory('tenants/' . $this->restaurant->slug . '/certificates')
                                    ->maxSize(1024)
                                    ->preserveFilenames(),
                                TextInput::make('sol_user')
                                    ->label('Usuario SOL')
                                    ->placeholder('Ej: MODDATOS')
                                    ->requiredWith('production'),

                                TextInput::make('sol_pass')
                                    ->label('Clave SOL')
                                    ->password() // Por seguridad
                                    ->revealable()
                                    ->requiredWith('production'),

                                TextInput::make('client_id')
                                    ->label('Client ID (Opcional)')
                                    ->helperText('Solo si usas la nueva API de SUNAT'),

                                TextInput::make('client_secret')
                                    ->label('Client Secret (Opcional)')
                                    ->password()
                                    ->revealable(),
                            ]),
                        ]),

                    Section::make('Contacto y Ubicación')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email(),
                                TextInput::make('phone')
                                    ->label('Teléfono/WhatsApp'),
                                TextInput::make('address')
                                    ->label('Dirección')
                                    ->columnSpanFull()
                                    ->required(),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('department')
                                    ->label('Departamento'),
                                TextInput::make('province')
                                    ->label('Provincia'),
                                TextInput::make('district')
                                    ->label('Distrito'),
                            ]),
                        ]),

                    Section::make('Identidad Visual')
                        ->schema([
                            FileUpload::make('logo')
                                ->label('Logo del Restaurante')
                                ->image()
                                ->imageEditor()
                                ->circleCropper()
                                ->disk('public')
                                ->directory('tenants/' . $this->restaurant->slug . '/restaurante')
                                ->optimize('webp', 90)
                        ]),
                ])
                ->action(function (array $data) {
                    // Actualizamos los datos del restaurante
                    $this->restaurant->update($data);

                    Cache::forget("restaurant_data_user_" . Auth::id());
                    $this->restaurant->refresh();

                    \Filament\Notifications\Notification::make()
                        ->title('Perfil actualizado correctamente')
                        ->success()
                        ->send();
                }),
        ];
    }
}

