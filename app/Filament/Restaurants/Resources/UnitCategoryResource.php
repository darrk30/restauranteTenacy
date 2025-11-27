<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\UnitCategoryResource\Pages;
use App\Filament\Restaurants\Resources\UnitCategoryResource\RelationManagers\UnitRelationManager;
use App\Models\UnitCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitCategoryResource extends Resource
{
    protected static ?string $model = UnitCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Unidades de Medida';

    protected static ?string $pluralModelLabel = 'Unidades de Medida';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
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
            UnitRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnitCategories::route('/'),
            'create' => Pages\CreateUnitCategory::route('/create'),
            'edit' => Pages\EditUnitCategory::route('/{record}/edit'),
        ];
    }
}
