<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Gestión de Empleados';
    protected static ?string $modelLabel = 'Rol';
    protected static ?string $pluralModelLabel = 'Roles y Permisos';
    protected static ?int $navigationSort = 160;

    public static function form(Form $form): Form
    {
        // 1. Obtenemos los permisos y los agrupamos por la columna 'module' (ej. inventario_productos)
        $modulos = Permission::where('scope', 'restaurant')
            ->orderBy('module_label')
            ->get()
            ->groupBy('module');

        $seccionesModulos = [];

        // 2. Iteramos usando 'module' como llave y 'permisosDelModulo' como valor
        foreach ($modulos as $moduleKey => $permisosDelModulo) {

            // Tomamos el 'module_label' del primer permiso de este grupo para ponerlo como título de la caja
            $tituloCaja = $permisosDelModulo->first()->module_label;
            
            // Usamos directamente el nombre de la columna 'module' como identificador
            $nombreCampo = 'permissions_' . $moduleKey;

            $seccionesModulos[] = Forms\Components\Section::make($tituloCaja)
                ->schema([
                    Forms\Components\CheckboxList::make($nombreCampo)
                        ->options($permisosDelModulo->pluck('description', 'name')->toArray())
                        ->label('')
                        ->columns(2)
                        ->bulkToggleable()
                        ->dehydrated(false)

                        // 🟢 PROTECCIÓN: Deshabilita los checkboxes si es Administrador Y el módulo es Gestión de Roles
                        // Ahora comparamos con $moduleKey porque esa es nuestra llave real
                        ->disabled(fn(?Model $record) => $record && $record->name === 'Administrador' && $moduleKey === 'roles_permisos')

                        ->afterStateHydrated(function ($component, ?Model $record) use ($permisosDelModulo) {
                            if ($record && $record->exists) {
                                $component->state(
                                    $record->permissions()
                                        ->pluck('name')
                                        ->intersect($permisosDelModulo->pluck('name'))
                                        ->values()
                                        ->toArray()
                                );
                            }
                        }),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpan(1);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Información del Rol')
                    ->description('Define el nombre del cargo para este empleado.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Rol (Ej: Cajero Turno Noche)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn(?Model $record) => $record && $record->name === 'Administrador'),

                        Forms\Components\Hidden::make('guard_name')
                            ->default('web')
                            ->saveRelationshipsUsing(function (Model $record, \Filament\Forms\Components\Component $component) {

                                $estadoDelFormulario = $component->getContainer()->getRawState();
                                $permisosParaGuardar = [];

                                foreach ($estadoDelFormulario as $key => $valores) {
                                    if (str_starts_with($key, 'permissions_') && is_array($valores)) {
                                        $permisosParaGuardar = array_merge($permisosParaGuardar, $valores);
                                    }
                                }

                                // 🟢 INYECCIÓN FORZADA: Si es Administrador, garantizamos los permisos de roles
                                if ($record->name === 'Administrador') {
                                    $permisosInquebrantables = Permission::where('module', 'roles_permisos')->pluck('name')->toArray();
                                    $permisosParaGuardar = array_unique(array_merge($permisosParaGuardar, $permisosInquebrantables));
                                }

                                $record->syncPermissions($permisosParaGuardar);
                            }),
                    ]),

                Forms\Components\Section::make('Asignación de Permisos Detallada')
                    ->description('Selecciona las acciones específicas que este rol podrá realizar en cada módulo.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema($seccionesModulos)
                    ]),
            ]);
    }

    // ... el resto de tu archivo (table, getRelations, getPages) se queda igual ...
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre del Rol')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permisos Asignados')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn(Role $record) => $record->name === 'Administrador'),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(Role $record) => $record->name === 'Administrador'),
                Tables\Actions\ViewAction::make()
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
            'view' => Pages\ViewRole::route('/{record}'),
        ];
    }
}