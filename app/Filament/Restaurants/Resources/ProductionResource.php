<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\ProductionResource\Pages;
use App\Filament\Restaurants\Resources\ProductionResource\RelationManagers;
use App\Models\Production;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static ?string $navigationIcon = 'heroicon-m-squares-plus';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Areas de Producción';

    protected static ?string $pluralModelLabel = 'Areas de Producción';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(2) // 👈 Dos columnas
                            ->schema([
                                TextInput::make('name')->label('Nombre')
                                    ->required()
                                    ->maxLength(255),

                                // Impresora (izquierda)
                                Forms\Components\Select::make('printer_id')
                                    ->label('Impresora asignada')
                                    ->relationship('printer', 'name')
                                    ->searchable()
                                    ->placeholder('Ninguna')
                                    ->preload()
                                    ->columnSpan(1),

                                // Status (derecha)
                                Forms\Components\Toggle::make('status')
                                    ->label('Publicado')
                                    ->default(true)
                                    ->inline(false) // se muestra como switch normal
                                    ->columnSpan(1),
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('printer.name')
                    ->label('Impresora')
                    ->placeholder('Ninguna')
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListProductions::route('/'),
            'create' => Pages\CreateProduction::route('/create'),
            'edit' => Pages\EditProduction::route('/{record}/edit'),
        ];
    }
}
