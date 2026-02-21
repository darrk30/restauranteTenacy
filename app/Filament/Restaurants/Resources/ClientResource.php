<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Models\TypeDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $modelLabel = 'Cliente';
        protected static ?string $navigationGroup = 'Caja';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identificación')
                    ->schema([
                        Grid::make(2)->schema([

                            Select::make('type_document_id')
                                ->label('Tipo de Documento')
                                ->relationship('typeDocument', 'code')
                                ->required()
                                ->default(1)
                                ->live() // 🔥 VITAL: Esto hace que el formulario se "refresque" al cambiar
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    // Opcional: Si cambian de DNI a RUC, limpiamos el número para que no quede uno inválido
                                    $set('numero', '');
                                }),

                            TextInput::make('numero')
                                ->label('Número')
                                ->required()
                                // 1. Validaciones y límites (Igual que antes)
                                ->maxLength(function (Get $get) {
                                    $typeId = $get('type_document_id');
                                    if ($typeId) {
                                        $limit = TypeDocument::find($typeId)?->maximo_carateres;
                                        return $limit ?? 20;
                                    }
                                    return 20;
                                })
                                ->rule(function (Get $get) {
                                    $typeId = $get('type_document_id');
                                    $limit = TypeDocument::find($typeId)?->maximo_carateres;
                                    return $limit ? "max_digits:$limit" : null;
                                })
                                ->numeric(function (Get $get) {
                                    $typeId = $get('type_document_id');
                                    $doc = TypeDocument::find($typeId);
                                    return $doc && $doc->code !== 'PASAPORTE';
                                })
                                ->unique(
                                    table: 'clients',
                                    column: 'numero',
                                    ignoreRecord: true,
                                    modifyRuleUsing: function ($rule, Get $get) {
                                        return $rule
                                            ->where('restaurant_id', filament()->getTenant()->id)
                                            ->where('type_document_id', $get('type_document_id'));
                                    }
                                )
                                ->validationMessages([
                                    'required' => 'El número es obligatorio.',
                                    'numeric' => 'El campo solo debe contener números.',
                                    'max_digits' => 'El número no debe tener más de :max dígitos.',
                                    'unique' => 'Este cliente ya está registrado.',
                                ])

                                // 🔥 2. ACCIÓN DE BÚSQUEDA CORREGIDA Y ROBUSTA
                                ->suffixAction(
                                    Action::make('buscar_sunat_reniec')
                                        ->icon('heroicon-m-magnifying-glass')
                                        ->color('primary')
                                        ->action(function (Get $get, callable $set) {
                                            // A. Validar que haya datos para buscar
                                            $typeId = $get('type_document_id');
                                            $numero = $get('numero');

                                            if (!$typeId || !$numero) {
                                                Notification::make()->title('Seleccione tipo y número primero')->warning()->send();
                                                return;
                                            }

                                            // B. Obtener el CÓDIGO real (DNI, RUC) desde la BD
                                            $docType = TypeDocument::find($typeId);
                                            if (!$docType) return;
                                            $codigo = $docType->code;

                                            // --- CASO RUC ---
                                            if ($codigo === 'RUC') {
                                                $data = \App\Services\DocumentoService::consultarRuc($numero);

                                                // Validación de error en respuesta
                                                if (!$data || isset($data['error']) || isset($data['message'])) {
                                                    Notification::make()->title('RUC no encontrado o error de servicio')->danger()->send();
                                                    return;
                                                }

                                                // 🛡️ MAPEO SEGURO (El truco para que no falle)
                                                // Busca 'razonSocial', si no existe busca 'razon_social', si no 'nombre'...
                                                $razonSocial = $data['razonSocial']
                                                    ?? $data['razon_social']
                                                    ?? $data['nombre']
                                                    ?? $data['nombre_o_razon_social']
                                                    ?? null;

                                                $direccion = $data['direccion']
                                                    ?? $data['direccion_completa']
                                                    ?? $data['domicilio_fiscal']
                                                    ?? '';

                                                if ($razonSocial) {
                                                    $set('razon_social', $razonSocial);
                                                    $set('direccion', $direccion);

                                                    // Limpiar campos de persona natural
                                                    $set('nombres', null);
                                                    $set('apellidos', null);

                                                    Notification::make()->title('Empresa encontrada')->success()->send();
                                                } else {
                                                    Notification::make()->title('La API no devolvió la Razón Social')->danger()->send();
                                                }
                                            }

                                            // --- CASO DNI ---
                                            elseif ($codigo === 'DNI') {
                                                $data = \App\Services\DocumentoService::consultarDni($numero);

                                                if (!$data || isset($data['error'])) {
                                                    Notification::make()->title('DNI no encontrado')->danger()->send();
                                                    return;
                                                }

                                                // Mapeo seguro para DNI
                                                $nombres = $data['nombres'] ?? $data['nombre'] ?? null;
                                                $apellidoP = $data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '';
                                                $apellidoM = $data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '';

                                                if ($nombres) {
                                                    $set('nombres', $nombres);
                                                    $set('apellidos', trim("$apellidoP $apellidoM"));

                                                    // Limpiar campos de empresa
                                                    $set('razon_social', null);

                                                    Notification::make()->title('Persona encontrada')->success()->send();
                                                } else {
                                                    Notification::make()->title('Datos incompletos del DNI')->danger()->send();
                                                }
                                            } else {
                                                Notification::make()->title('Búsqueda no disponible para ' . $codigo)->info()->send();
                                            }
                                        })
                                ),
                        ]),
                    ]),

                Section::make('Datos del Cliente')
                    ->schema([
                        // CASO A: EMPRESA (RUC)
                        TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->required()
                            ->columnSpanFull()
                            ->visible(function (Get $get) {
                                // Lógica: Mostrar si es RUC (ID 2 o Código 'RUC')
                                $typeId = $get('type_document_id');
                                if (!$typeId) return false;
                                $doc = TypeDocument::find($typeId);
                                return $doc && $doc->code === 'RUC';
                            }),

                        // CASO B: PERSONA (NO RUC)
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nombres')
                                    ->label('Nombres')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('apellidos')
                                    ->label('Apellidos')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->visible(function (Get $get) {
                                // Lógica inversa: Mostrar si NO es RUC
                                $typeId = $get('type_document_id');
                                if (!$typeId) return true; // Por defecto mostrar
                                $doc = TypeDocument::find($typeId);
                                return $doc && $doc->code !== 'RUC';
                            }),

                        // DATOS COMUNES
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->placeholder('Obligatorio para facturas')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->maxLength(255),

                            TextInput::make('telefono')
                                ->label('Teléfono / Celular')
                                ->tel()
                                ->maxLength(20),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with('typeDocument'))
            ->columns([
                // 1. TIPO Y NÚMERO
                Tables\Columns\TextColumn::make('typeDocument.code')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'RUC' => 'warning',
                        'DNI' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->weight('bold'),

                // 2. LAS 3 COLUMNAS QUE PEDISTE (Simplificadas)
                Tables\Columns\TextColumn::make('nombres')
                    ->label('Nombres')
                    ->searchable()
                    ->placeholder('Sin dato'), // Esto lo pone gris automático

                Tables\Columns\TextColumn::make('apellidos')
                    ->label('Apellidos')
                    ->searchable()
                    ->placeholder('Sin dato'),

                Tables\Columns\TextColumn::make('razon_social')
                    ->label('Razón Social')
                    ->searchable()
                    ->placeholder('Sin dato'),

                // 3. CONTACTO
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->toggleable(),
            ])
            ->filters([
                // ... tus filtros igual que antes ...
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // Ocultar si es el cliente genérico
                    ->hidden(fn(Client $record) => $record->numero === '99999999'),

                Tables\Actions\DeleteAction::make()
                    // Ocultar si es el cliente genérico
                    ->hidden(fn(Client $record) => $record->numero === '99999999'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        // Evita que se elimine incluso si se selecciona en grupo
                        ->action(function (Collection $records) {
                            $records->filter(fn($record) => $record->numero !== '99999999')
                                ->each(fn($record) => $record->delete());
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
