<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\FloorResource\RelationManagers\TablesRelationManager;
use App\Filament\Restaurants\Resources\FloorResource\Pages\ListFloors;
use App\Filament\Restaurants\Resources\FloorResource\Pages\CreateFloor;
use App\Filament\Restaurants\Resources\FloorResource\Pages\EditFloor;
use App\Filament\Restaurants\Resources\FloorResource\Pages;
use App\Filament\Restaurants\Resources\FloorResource\RelationManagers;
use App\Models\Floor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FloorResource extends Resource
{
    protected static ?string $model = Floor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';


    // protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Pisos';

    protected static ?string $pluralModelLabel = 'Pisos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(2) // 👈 dos columnas
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('printer_id')
                                    ->label('Impresora asignada')
                                    ->relationship('printer', 'name')
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Toggle::make('status')
                            ->label('Activo')
                            ->required()
                            ->default(true)
                            ->inline(false), // 👈 ocupa toda la fila
                    ])
                    ->columns(1), // Card con una columna interna
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
                EditAction::make()->label('Editar'),
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
            TablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFloors::route('/'),
            'create' => CreateFloor::route('/create'),
            'edit' => EditFloor::route('/{record}/edit'),
        ];
    }
}
