<?php

namespace App\Filament\Clusters\Products\Resources;

use App\Filament\Clusters\Products\ProductsCluster;
use App\Filament\Clusters\Products\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $cluster = ProductsCluster::class;

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->label('Nombre')
                    ->required()
                    ->maxLength(255),
                    
                Toggle::make('status')->label('Publicado')
                    ->required()
                    ->default(true)
                    ->inline(false),
                
                // Opcional: puedes agregar el campo en el form si quieres editarlo manualmente
                // aunque con la funcionalidad de arrastrar en la tabla no suele ser necesario.
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 1. Habilitamos la función de arrastrar para ordenar
            ->reorderable('sort_order') 
            // 2. Establecemos el orden por defecto para que la tabla coincida con la lógica
            ->defaultSort('sort_order') 
            ->columns([
                TextColumn::make('name')->label('Nombre')
                    ->searchable(),
                IconColumn::make('status')->label('Publicado')
                    ->boolean(),
                // Agregamos la columna de orden solo para visualizar (opcional)
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
            'index' => ListCategories::route('/'),
        ];
    }
}