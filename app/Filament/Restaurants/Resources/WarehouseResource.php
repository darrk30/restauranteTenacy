<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Filament\Restaurants\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';


    // protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Almacenes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Almacenes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(50),

                TextInput::make('direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(200),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order')     // permitir arrastrar filas y guardar el orden
            ->defaultSort('id', 'asc')     // ordenar por defecto por order ASC
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->limit(30),
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
            'index' => ListWarehouses::route('/'),
            // 'create' => Pages\CreateWarehouse::route('/create'),
            // 'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
