<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\UnitCategoryResource\Pages\ListUnitCategories;
use App\Filament\Restaurants\Resources\UnitCategoryResource\Pages\CreateUnitCategory;
use App\Filament\Restaurants\Resources\UnitCategoryResource\Pages\EditUnitCategory;
use App\Filament\Restaurants\Resources\UnitCategoryResource\Pages;
use App\Filament\Restaurants\Resources\UnitCategoryResource\RelationManagers\UnitRelationManager;
use App\Models\UnitCategory;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitCategoryResource extends Resource
{
    protected static ?string $model = UnitCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';


    // protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Unidades de Medida';

    protected static ?string $pluralModelLabel = 'Unidades de Medida';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
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
            UnitRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitCategories::route('/'),
            'create' => CreateUnitCategory::route('/create'),
            'edit' => EditUnitCategory::route('/{record}/edit'),
        ];
    }
}
