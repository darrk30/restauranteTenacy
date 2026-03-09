<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role; // 🟢 Usamos el modelo nativo de Spatie para roles globales
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Seguridad SaaS';
    protected static ?string $modelLabel = 'Rol Global';
    protected static ?string $pluralModelLabel = 'Roles del Sistema';

    // 🟢 Solo mostramos roles que NO pertenezcan a un restaurante (Roles Globales)
    public static function getEloquentQuery(): Builder
    {
        $teamField = config('permission.column_names.team_foreign_key', 'restaurant_id');
        return parent::getEloquentQuery()->whereNull($teamField);
    }

    public static function form(Form $form): Form
    {
        // 1. Obtenemos permisos GLOBAL (Exclusivos del panel SaaS)
        $modulos = Permission::where('scope', 'global')
            ->orderBy('module_label')
            ->get()
            ->groupBy('module_label');

        $seccionesModulos = [];

        foreach ($modulos as $moduleLabel => $permisosDelModulo) {
            $nombreCampo = 'permissions_' . Str::slug($moduleLabel);

            $seccionesModulos[] = Forms\Components\Section::make($moduleLabel)
                ->schema([
                    Forms\Components\CheckboxList::make($nombreCampo)
                        ->options($permisosDelModulo->pluck('description', 'name')->toArray())
                        ->label('')
                        ->columns(2)
                        ->bulkToggleable()
                        ->dehydrated(false)
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
                ])->collapsible()->collapsed()->columnSpan(1);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Información del Rol SaaS')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Rol (Ej: Soporte Técnico)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                            
                        Forms\Components\Hidden::make('guard_name')->default('web'),
                        
                        // 🟢 Forzamos que el Rol sea global (sin restaurante asignado)
                        Forms\Components\Hidden::make(config('permission.column_names.team_foreign_key', 'restaurant_id'))
                            ->default(null),

                        Forms\Components\Hidden::make('permissions_sync')
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function (Model $record, Form $form) {
                                $estadoDelFormulario = $form->getRawState();
                                $permisosParaGuardar = [];
                                
                                foreach ($estadoDelFormulario as $key => $valores) {
                                    if (str_starts_with($key, 'permissions_') && is_array($valores)) {
                                        $permisosParaGuardar = array_merge($permisosParaGuardar, $valores);
                                    }
                                }
                                
                                // Para roles globales, temporalmente quitamos el team_id de Spatie
                                $teamIdOriginal = getPermissionsTeamId();
                                setPermissionsTeamId(null);
                                
                                $record->syncPermissions($permisosParaGuardar);
                                
                                setPermissionsTeamId($teamIdOriginal);
                            }),
                    ]),

                Forms\Components\Section::make('Permisos Administrativos')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema($seccionesModulos)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol Global')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permisos')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn (Role $record) => $record->name === 'Super Admin'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}