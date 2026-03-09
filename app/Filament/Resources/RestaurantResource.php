<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Resources\RestaurantResource\RelationManagers\UsersRelationManager;
use App\Models\Restaurant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Gestión SaaS';
    protected static ?string $modelLabel = 'Restaurante';
    protected static ?string $pluralModelLabel = 'Restaurantes Clientes';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Super Admin') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Logo e Identidad')
                    ->description('Sube el logotipo del restaurante. Se mostrará en su panel y menús.')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logotipo')
                            ->image()
                            ->directory('logos_restaurantes')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),

                Section::make('Datos Principales')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Razón Social / Nombre del Restaurante')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(
                                    fn(string $operation, $state, Set $set) =>
                                    $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                ),

                            TextInput::make('slug')
                                ->label('Slug (URL única)')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('Se usa para el subdominio: mi-restaurante.kipu.cloud'),

                            TextInput::make('name_comercial')
                                ->label('Nombre Comercial')
                                ->maxLength(255),

                            TextInput::make('ruc')
                                ->label('RUC')
                                ->required()
                                ->maxLength(11),
                        ])
                    ]),

                Section::make('Contacto y Ubicación')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('email')->email()->maxLength(255),
                            TextInput::make('phone')->tel()->maxLength(20),
                            TextInput::make('department')->label('Departamento')->maxLength(255),
                            TextInput::make('province')->label('Provincia')->maxLength(255),
                            TextInput::make('district')->label('Distrito')->maxLength(255),
                            TextInput::make('ubigeo')->label('Ubigeo')->maxLength(10),
                            TextInput::make('address')->label('Dirección Exacta')->required()->columnSpanFull(),
                        ])
                    ]),

                Section::make('Estado del Servicio')
                    ->schema([
                        // 🟢 1. SWITCH EN EL FORMULARIO
                        Toggle::make('status')
                            ->label('Permitir Acceso al Sistema')
                            ->onColor('success')
                            ->offColor('danger')
                            // Filament espera true/false en el switch, así que transformamos el string de la BD
                            ->formatStateUsing(fn($state) => $state === 'activo' || $state === true)
                            // Al guardar, transformamos el true/false de vuelta al string 'activo'/'inactivo'
                            ->dehydrateStateUsing(fn($state) => $state ? 'activo' : 'inactivo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Restaurante')
                    ->searchable()
                    ->weight('bold')
                    // Agregamos el subdominio debajo del nombre para que se vea genial
                    ->description(fn(Restaurant $record): string => $record->slug . '.' . config('app.domain', 'kipu.cloud')),

                TextColumn::make('ruc')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Personal')
                    ->badge()
                    ->color('info'),

                // 🟢 2. SWITCH INTERACTIVO EN LA TABLA
                ToggleColumn::make('status_toggle')
                    ->label('Acceso')
                    ->onColor('success')
                    ->offColor('danger')
                    ->getStateUsing(fn(Restaurant $record) => $record->status === 'activo')
                    ->updateStateUsing(function (Restaurant $record, $state) {
                        // 1. Guardamos el nuevo estado en la base de datos
                        $nuevoEstado = $state ? 'activo' : 'inactivo';
                        $record->update(['status' => $nuevoEstado]);

                        // 2. Preparamos el mensaje personalizado
                        $mensaje = $state
                            ? 'El restaurante ahora tiene acceso al sistema.'
                            : 'Se ha suspendido el acceso al restaurante.';

                        // 3. Lanzamos la notificación verde flotante
                        Notification::make()
                            ->title('Estado actualizado')
                            ->body($mensaje)
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                // 🟢 3. BOTÓN CON LINK AL SISTEMA DEL CLIENTE
                Tables\Actions\Action::make('visitar_app')
                    ->label('Ir al Sistema')
                    ->hiddenLabel()
                    ->iconButton()
                    ->tooltip('Abrir el sistema de este cliente')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    // Genera la URL dinámicamente: http://slug.dominio.com/app
                    ->url(fn(Restaurant $record): string => 'http://' . $record->slug . '.' . config('app.domain', 'localhost') . '/app')
                    ->openUrlInNewTab(), // Lo abre en una pestaña nueva

                Tables\Actions\EditAction::make()
                    ->hiddenLabel()
                    ->iconButton()
                    ->tooltip('Editar datos del cliente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit' => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
}
