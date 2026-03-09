<?php

namespace App\Filament\Resources\RestaurantResource\RelationManagers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Infolists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Personal del Restaurante';
    protected static ?string $icon = 'heroicon-o-users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña Temporal')
                    ->password()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(fn(?string $state) => filled($state))
                    ->dehydrateStateUsing(fn(string $state) => Hash::make($state)),
            ]);
    }

    public function table(Table $table): Table
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles Asignados')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (User $record) {
                        return $record->roles()->pluck('name');
                    }),
            ])
            ->filters([])
            ->headerActions([
                // 🟢 BOTÓN: NUEVO USUARIO
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo Usuario')
                    ->iconButton()
                    ->hiddenLabel() // 🟢 Oculta el texto
                    ->tooltip('Crear Nuevo Usuario') // 🟢 Tooltip al pasar el mouse
                    ->icon('heroicon-o-user-plus') // Mejoré el icono a uno de "usuario nuevo"
                    ->color('primary'),

                // 🟢 BOTÓN: VINCULAR EXISTENTE
                Tables\Actions\AttachAction::make()
                    ->label('Vincular Existente')
                    ->iconButton()
                    ->hiddenLabel() // 🟢 Oculta el texto
                    ->tooltip('Vincular Usuario Existente') // 🟢 Tooltip al pasar el mouse
                    ->icon('heroicon-o-link')
                    ->color('gray') // Color gris para diferenciarlo del principal
                    ->preloadRecordSelect()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('role')
                            ->label('Asignar Rol Inicial')
                            ->options(Role::where('restaurant_id', $this->getOwnerRecord()->id)->pluck('name', 'name'))
                            ->required(),
                    ])
                    ->after(function (User $record, array $data) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);
                        $record->assignRole($data['role']);
                    }),
            ])
            ->actions([
                // 🟢 1. BOTÓN: VER ACCESOS (SOLO LECTURA)
                Action::make('viewPermissions')
                    ->label('Ver Accesos') // Texto base
                    ->iconButton()
                    ->hiddenLabel() // 🟢 Oculta el texto en la tabla
                    ->tooltip('Ver Accesos') // 🟢 Muestra el globo negro al pasar el mouse
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->modalHeading(fn(User $record) => 'Accesos de ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(function (User $record) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);

                        $roles = $record->roles()->pluck('name');
                        $permisosAgrupados = $record->getAllPermissions()->groupBy('module_label');

                        $seccionesDePermisos = [];

                        foreach ($permisosAgrupados as $modulo => $permisos) {
                            $seccionesDePermisos[] = Infolists\Components\TextEntry::make('modulo_' . $modulo)
                                ->label($modulo)
                                ->badge()
                                ->color('success')
                                ->getStateUsing(fn() => $permisos->pluck('description')->toArray());
                        }

                        if (empty($seccionesDePermisos)) {
                            $seccionesDePermisos[] = Infolists\Components\TextEntry::make('sin_acceso')
                                ->label('')
                                ->getStateUsing(fn() => 'Este usuario no tiene ningún permiso asignado en este restaurante.')
                                ->color('danger');
                        }

                        return [
                            Infolists\Components\Section::make('Cargos Actuales')
                                ->schema([
                                    Infolists\Components\TextEntry::make('roles_asignados')
                                        ->label('')
                                        ->badge()
                                        ->color('warning')
                                        ->getStateUsing(fn() => $roles->isEmpty() ? ['Ningún rol'] : $roles->toArray()),
                                ]),

                            Infolists\Components\Section::make('Permisos Efectivos Totales')
                                ->schema([
                                    Infolists\Components\Grid::make(2)->schema($seccionesDePermisos)
                                ])
                        ];
                    }),

                // 🟢 2. BOTÓN MAESTRO: ADMINISTRAR ROLES Y PERMISOS INDIVIDUALES
                Action::make('manageAccess')
                    ->label('Administrar rol') // El texto real del botón (para el tooltip y accesibilidad)
                    ->iconButton()
                    ->hiddenLabel() // Oculta el texto en la tabla, dejando SOLO el icono
                    ->tooltip('Administrar rol') // Muestra el texto flotante al pasar el mouse
                    ->icon('heroicon-o-shield-check') // Cambiado a icono de escudo con check
                    ->color('warning')
                    ->modalHeading(fn(User $record) => 'Control de Accesos: ' . $record->name)
                    ->modalWidth('4xl')
                    ->form(function () {
                        $restaurantId = $this->getOwnerRecord()->id;

                        // Obtenemos módulos y roles
                        $modulos = \App\Models\Permission::where('scope', 'restaurant')->orderBy('module_label')->get()->groupBy('module_label');
                        $todosLosRoles = \Spatie\Permission\Models\Role::with('permissions')->where('restaurant_id', $restaurantId)->get();

                        $seccionesModulos = [];

                        foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                            $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($moduleLabel);

                            $seccionesModulos[] = Forms\Components\Section::make($moduleLabel)
                                ->schema([
                                    Forms\Components\CheckboxList::make($nombreCampo)
                                        ->options($permisosDelModulo->pluck('description', 'name')->toArray())
                                        ->label('')
                                        ->columns(1)
                                        ->bulkToggleable()

                                        // 🟢 MAGIA 1: Bloqueamos (ponemos en gris) los permisos que ya vienen con el rol
                                        ->disableOptionWhen(function (string $value, Forms\Get $get) use ($todosLosRoles) {
                                            $roleNames = $get('roles') ?? [];
                                            if (empty($roleNames)) return false;
                                            $rolePermissions = $todosLosRoles->whereIn('name', $roleNames)->flatMap->permissions->pluck('name')->toArray();
                                            return in_array($value, $rolePermissions);
                                        })

                                        // 🟢 Mostramos el texto del candado en los que están bloqueados
                                        ->descriptions(function (Forms\Get $get) use ($todosLosRoles, $permisosDelModulo) {
                                            $roleNames = $get('roles') ?? [];
                                            if (empty($roleNames)) return [];

                                            $rolePermissions = $todosLosRoles->whereIn('name', $roleNames)->flatMap->permissions->pluck('name')->toArray();

                                            $descripciones = [];
                                            foreach ($permisosDelModulo as $permiso) {
                                                if (in_array($permiso->name, $rolePermissions)) {
                                                    $descripciones[$permiso->name] = '🔒 (Bloqueado)';
                                                }
                                            }
                                            return $descripciones;
                                        })
                                ])->collapsible()->collapsed()->columnSpan(1);
                        }

                        return [
                            Forms\Components\Section::make('Cargos (Roles)')
                                ->schema([
                                    Forms\Components\Select::make('roles')
                                        ->label('Roles Asignados')
                                        ->multiple()
                                        ->options($todosLosRoles->pluck('name', 'name'))
                                        ->searchable()
                                        ->preload()
                                        ->live()

                                        // 🟢 MAGIA 2: Al cambiar el Rol, marcamos al instante las casillas de abajo
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) use ($todosLosRoles, $modulos) {
                                            $rolePermissions = $todosLosRoles->whereIn('name', $state ?? [])->flatMap->permissions->pluck('name')->toArray();

                                            foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                                                $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($moduleLabel);
                                                $currentChecked = $get($nombreCampo) ?? [];
                                                // Mezclamos los permisos extra que ya tenía marcados con los nuevos del rol
                                                $merged = array_unique(array_merge($currentChecked, $rolePermissions));
                                                $set($nombreCampo, collect($merged)->intersect($permisosDelModulo->pluck('name'))->values()->toArray());
                                            }
                                        })
                                ]),

                            Forms\Components\Section::make('Permisos Extra a Medida')
                                ->description('Las casillas grises (🔒) ya vienen incluidas en su rol y no se pueden quitar. Marca las casillas libres para darle permisos extra.')
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema($seccionesModulos)
                                ])
                        ];
                    })
                    ->mountUsing(function (Forms\Form $form, User $record) {
                        $restaurantId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($restaurantId);

                        // Cargamos los roles
                        $data = [
                            'roles' => $record->roles()->pluck('name')->toArray(),
                        ];

                        // Cargamos TODOS los permisos para que inicien marcados
                        $modulos = \App\Models\Permission::where('scope', 'restaurant')->get()->groupBy('module_label');
                        $todosLosPermisos = $record->getAllPermissions()->pluck('name')->toArray();

                        foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                            $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($moduleLabel);
                            $data[$nombreCampo] = collect($todosLosPermisos)
                                ->intersect($permisosDelModulo->pluck('name'))
                                ->values()
                                ->toArray();
                        }

                        $form->fill($data);
                    })
                    ->action(function (User $record, array $data) {
                        $restaurantId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($restaurantId);

                        // 1. Sincronizamos los roles
                        $record->syncRoles($data['roles'] ?? []);

                        // 2. Juntamos los permisos libres que marcaste 
                        // (Los que están bloqueados en gris Filament los ignora automáticamente, lo cual es perfecto para nosotros)
                        $permisosMarcados = [];
                        foreach ($data as $key => $valores) {
                            if (str_starts_with($key, 'permissions_') && is_array($valores)) {
                                $permisosMarcados = array_merge($permisosMarcados, $valores);
                            }
                        }

                        // 3. Forzamos la limpieza en la base de datos
                        DB::table('model_has_permissions')
                            ->where('model_id', $record->id)
                            ->where('model_type', get_class($record))
                            ->where('restaurant_id', $restaurantId)
                            ->delete();

                        // 4. Doble filtro de seguridad: Quitamos los permisos del rol por si acaso
                        $record->forgetCachedPermissions();
                        $permisosDelRol = $record->getPermissionsViaRoles()->pluck('name')->toArray();
                        $permisosExtra = array_diff($permisosMarcados, $permisosDelRol);

                        // 5. Guardamos en BD
                        if (!empty($permisosExtra)) {
                            $permisosObj = \App\Models\Permission::whereIn('name', $permisosExtra)->get();

                            $insertData = [];
                            foreach ($permisosObj as $permiso) {
                                $insertData[] = [
                                    'permission_id' => $permiso->id,
                                    'model_type' => get_class($record),
                                    'model_id' => $record->id,
                                    'restaurant_id' => $restaurantId,
                                ];
                            }

                            DB::table('model_has_permissions')->insert($insertData);
                        }

                        $record->forgetCachedPermissions();
                    }),

                // 🟢 3. BOTÓN: DESVINCULAR
                Tables\Actions\DetachAction::make()
                    ->label('Desvincular') // Texto base
                    ->iconButton()
                    ->hiddenLabel() // 🟢 Oculta el texto en la tabla
                    ->tooltip('Desvincular empleado') // 🟢 Muestra el globo negro al pasar el mouse
                    ->icon('heroicon-o-user-minus') // 🟢 Icono ideal para "quitar usuario"
                    ->color('danger')
                    ->modalHeading('Quitar empleado del restaurante')
                    ->before(function (User $record) {
                        $restaurantId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($restaurantId);

                        // Le quitamos roles
                        $record->syncRoles([]);

                        // Le quitamos permisos extra
                        DB::table(config('permission.table_names.model_has_permissions'))
                            ->where('model_id', $record->id)
                            ->where('model_type', get_class($record))
                            ->where('restaurant_id', $restaurantId)
                            ->delete();

                        $record->forgetCachedPermissions();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $restaurantId = $this->getOwnerRecord()->id;
                            app(PermissionRegistrar::class)->setPermissionsTeamId($restaurantId);

                            foreach ($records as $record) {
                                $record->syncRoles([]);
                                DB::table(config('permission.table_names.model_has_permissions'))
                                    ->where('model_id', $record->id)
                                    ->where('model_type', get_class($record))
                                    ->where('restaurant_id', $restaurantId)
                                    ->delete();
                                $record->forgetCachedPermissions();
                            }
                        }),
                ]),
            ]);
    }
}
