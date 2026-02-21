<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Métodos de Pago';
    protected static ?string $pluralModelLabel = 'Métodos de Pago';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

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
                        FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->disk('public')
                            ->directory('tenants/' . Filament::getTenant()->slug . '/metodos_pago')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->columnSpanFull(),

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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
