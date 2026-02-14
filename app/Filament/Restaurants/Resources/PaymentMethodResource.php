<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\PaymentMethodResource\Pages\ListPaymentMethods;
use App\Filament\Restaurants\Resources\PaymentMethodResource\Pages\CreatePaymentMethod;
use App\Filament\Restaurants\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\ImageColumn;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Métodos de Pago';
    // protected static ?string $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Información del método de pago')
                    ->description('Configura los datos principales del método.')
                    ->schema([
                        // Campos principales
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),


                        TextInput::make('payment_condition')
                            ->label('Condición de pago')
                            ->placeholder('Ej: Contado, Crédito, Mixto')
                            ->required(),

                        Toggle::make('requiere_referencia')
                            ->label('¿Requiere referencia?')
                            ->inline(false)
                            ->helperText('Ej: para pagos con depósito, transferencia, etc.')
                            ->columnSpan(1),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'activo' => 'Activo',
                                'inactivo' => 'Inactivo',
                            ])
                            ->default('activo')
                            ->required(),
                        // Imagen arriba ocupando todo el ancho
                        FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->directory('metodos_pago')
                            ->disk('public')
                            ->preserveFilenames()
                            ->previewable(true),

                    ])
                    ->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Logo')
                    ->circular(),
                    
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_condition')
                    ->label('Condición')
                    ->searchable(),

                IconColumn::make('requiere_referencia')
                    ->label('Referencia')
                    ->boolean(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === 'activo',
                        'danger' => fn($state) => $state === 'inactivo',
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
