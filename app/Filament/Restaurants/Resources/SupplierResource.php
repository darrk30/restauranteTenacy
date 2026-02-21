<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use App\Services\DocumentoService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Validation\Rules\Unique;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Proveedores';
    protected static ?string $pluralLabel = 'Proveedores';
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 44;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->description('Información básica del proveedor')
                    ->schema([
                        TextInput::make('name')
                            ->label('Razón Social / Nombre')
                            ->required()
                            ->maxLength(255),

                        Select::make('tipo_documento')
                            ->options([
                                'RUC' => 'RUC',
                                'DNI' => 'DNI',
                            ])
                            ->native(false)
                            ->label('Tipo de documento')
                            ->reactive(),

                        TextInput::make('numero')
                            ->label('Número de documento')
                            ->numeric()
                            ->reactive()
                            ->required()
                            ->validationMessages([
                                'unique'   => 'Ya existe un proveedor con este número de documento.',
                                'required' => 'El número de documento es obligatorio.',
                            ])
                            ->unique(
                                table: Supplier::class,
                                column: 'numero',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule) {
                                    return $rule->where('restaurant_id', filament()->getTenant()->id);
                                }
                            )
                            ->suffixAction(
                                Action::make('buscar')
                                    ->label('Buscar')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->action(function (callable $get, callable $set) {
                                        $tipo = $get('tipo_documento');
                                        $numero = $get('numero');
                                        if (!$tipo || !$numero) {
                                            Notification::make()
                                                ->title('Seleccione tipo y número')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        if ($tipo === 'RUC') {
                                            $data = DocumentoService::consultarRuc($numero);
                                            if (!$data || !isset($data['razonSocial'])) {
                                                Notification::make()
                                                    ->title('RUC no encontrado')
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }
                                            // AUTOCOMPLETAR
                                            $set('name', $data['razonSocial']);
                                            $set('direccion', $data['direccion'] ?? null);
                                            $set('departamento', $data['departamento'] ?? null);
                                            $set('provincia', $data['provincia'] ?? null);
                                            $set('distrito', $data['distrito'] ?? null);
                                        }

                                        if ($tipo === 'DNI') {
                                            $data = DocumentoService::consultarDni($numero);
                                            if (!$data || !isset($data['nombres'])) {
                                                Notification::make()
                                                    ->title('DNI no encontrado')
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }
                                            $set('name', trim(
                                                ($data['nombres'] ?? '') . ' ' .
                                                    ($data['apellidoPaterno'] ?? '') . ' ' .
                                                    ($data['apellidoMaterno'] ?? '')
                                            ));
                                        }
                                        Notification::make()->title('Datos cargados correctamente')->success()->send();
                                    })
                            ),
                    ])
                    ->columns(3),

                Section::make('Contacto')
                    ->schema([
                        TextInput::make('correo')
                            ->email()
                            ->label('Correo'),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->numeric()
                            ->maxLength(9)
                            ->rules(['digits_between:6,9'])
                            ->hint('Debe contener entre 6 y 9 dígitos'),
                    ])
                    ->columns(2),

                Section::make('Ubicación')
                    ->schema([
                        TextInput::make('direccion')->label('Dirección'),
                        TextInput::make('departamento')->label('Departamento'),
                    ])
                    ->columns(2),

                Section::make('Estado')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'activo' => 'Activo',
                                'inactivo' => 'Inactivo',
                                'archivado' => 'Archivado',
                            ])
                            ->native(false)
                            ->default('activo')
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo_documento')
                    ->label('Documento')
                    ->sortable(),

                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('telefono')
                    ->label('Teléfono'),

                TextColumn::make('status')
                    ->badge()
                    ->label('Estado')
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                        'gray' => 'archivado',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'activo',
                        'heroicon-o-x-circle' => 'inactivo',
                        'heroicon-o-archive-box' => 'archivado',
                    ])
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'archivado' => 'Archivado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('compras')
                    ->label('Compras')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('warning')
                    ->url(fn($record) => static::getUrl('compras', ['record' => $record])),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'compras' => Pages\ViewProviderPurchases::route('/{record}/compras'),
            'compra-detalle' => Pages\ViewPurchaseDetail::route('/{record}/compras/detalle'),
        ];
    }
}
