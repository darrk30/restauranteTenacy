<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Gestión de Empleados';

    protected static ?int $navigationSort = 120;

    protected static ?string $navigationLabel = 'Personal / Usuarios';

    protected static ?string $pluralModelLabel = 'Personal del Restaurante';

    protected static ?string $tenantOwnershipRelationshipName = 'restaurants';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        $miSucursalId = Auth::user()->restaurants()->first()?->id;

        return $form
            ->schema([
                // 🟢 SECCIÓN 1: DATOS GENERALES
                Forms\Components\Section::make('Información del Empleado')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre Completo')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            Forms\Components\Select::make('roles')
                                ->label('Rol en Tienda')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->options(function () use ($miSucursalId) {
                                    return Role::where('restaurant_id', $miSucursalId)->pluck('name', 'name');
                                })
                                ->loadStateFromRelationshipsUsing(function ($component, $state, User $record) use ($miSucursalId) {
                                    if ($record->exists) {
                                        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($miSucursalId);
                                        $component->state($record->roles()->pluck('name')->toArray());
                                    }
                                })
                                ->saveRelationshipsUsing(function (User $record, $state) use ($miSucursalId) {
                                    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($miSucursalId);
                                    $record->syncRoles($state ?? []);
                                })
                                ->dehydrated(false)
                        ])
                    ]),

                // 🟢 SECCIÓN 2: SEGURIDAD (CONTRASEÑA)
                Forms\Components\Section::make('Seguridad de la Cuenta')
                    ->description('Administración de la clave de acceso al sistema.')
                    ->schema([

                        // Este interruptor SOLO aparece cuando estamos EDITANDO un usuario existente
                        Forms\Components\Toggle::make('change_password')
                            ->label('Cambiar la contraseña de este empleado')
                            ->live() // Hace que el formulario reaccione al instante al hacer clic
                            ->hidden(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(false), // No queremos que guarde este campo "change_password" en la BD

                        Forms\Components\Grid::make(2)->schema([

                            // Campo de Contraseña
                            Forms\Components\TextInput::make('password')
                                ->label(fn(string $operation) => $operation === 'create' ? 'Contraseña' : 'Nueva Contraseña')
                                ->password()
                                ->revealable()
                                ->confirmed() // 🟢 MÁGIA: Exige que coincida con "password_confirmation"
                                // Obligatorio si es usuario nuevo, o si el check de editar está encendido
                                ->required(fn(string $operation, Forms\Get $get): bool => $operation === 'create' || $get('change_password') === true)
                                // Visible si es usuario nuevo, o si el check de editar está encendido
                                ->visible(fn(string $operation, Forms\Get $get): bool => $operation === 'create' || $get('change_password') === true)
                                ->dehydrated(fn(?string $state) => filled($state))
                                ->dehydrateStateUsing(fn(string $state) => Hash::make($state)),

                            // Campo de Confirmación
                            Forms\Components\TextInput::make('password_confirmation')
                                ->label('Confirmar Contraseña')
                                ->password()
                                ->revealable()
                                // Obligatorio y visible bajo las mismas reglas que el campo de arriba
                                ->required(fn(string $operation, Forms\Get $get): bool => $operation === 'create' || $get('change_password') === true)
                                ->visible(fn(string $operation, Forms\Get $get): bool => $operation === 'create' || $get('change_password') === true)
                                ->dehydrated(false), // 🟢 Nunca se guarda en la base de datos, solo sirve para validar
                        ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->color('gray'),

                // 🟢 CORRECCIÓN APLICADA: Muestra solo los roles de ESTA sucursal
                Tables\Columns\TextColumn::make('cargos_asignados')
                    ->label('Cargo(s)')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (User $record) {
                        $miSucursalId = Auth::user()->restaurants()->first()?->id;
                        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($miSucursalId);
                        return $record->roles()->pluck('name');
                    })
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
                        $miSucursalId = Auth::user()->restaurants()->first()?->id;
                        $query->whereHas('roles', function ($q) use ($search, $miSucursalId) {
                            $q->where('name', 'like', "%{$search}%")
                                ->where('restaurant_id', $miSucursalId);
                        });
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hiddenLabel()
                    ->tooltip('Editar empleado')
                    ->color('warning'),

                Tables\Actions\DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Eliminar empleado'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
