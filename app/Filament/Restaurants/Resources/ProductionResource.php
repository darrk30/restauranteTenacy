<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\ProductionResource\Pages\ListProductions;
use App\Filament\Restaurants\Resources\ProductionResource\Pages\CreateProduction;
use App\Filament\Restaurants\Resources\ProductionResource\Pages\EditProduction;
use App\Filament\Restaurants\Resources\ProductionResource\Pages;
use App\Models\Production;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';


    // protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Areas de Producción';

    protected static ?string $pluralModelLabel = 'Areas de Producción';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(2) // 👈 Dos columnas
                            ->schema([
                                TextInput::make('name')->label('Nombre')
                                    ->required()
                                    ->maxLength(255),

                                // Impresora (izquierda)
                                Select::make('printer_id')
                                    ->label('Impresora asignada')
                                    ->relationship('printer', 'name')
                                    ->searchable()
                                    ->placeholder('Ninguna')
                                    ->preload()
                                    ->columnSpan(1),

                                // Status (derecha)
                                Toggle::make('status')
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
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('printer.name')
                    ->label('Impresora')
                    ->placeholder('Ninguna')
                    ->sortable(),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListProductions::route('/'),
            'create' => CreateProduction::route('/create'),
            'edit' => EditProduction::route('/{record}/edit'),
        ];
    }
}
