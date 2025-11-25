<?php

namespace App\Filament\Restaurants\Resources\PromotionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionProductRelationManager extends RelationManager
{
    protected static string $relationship = 'promotionproducts';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) {
                            $set('variant_id', null);
                            return;
                        }

                        // Buscar variantes activas del producto
                        $variants = \App\Models\Variant::where('product_id', $state)
                            ->where('status', 'activo')
                            ->get();

                        // Si hay exactamente UNA → seleccionarla automáticamente
                        if ($variants->count() === 1) {
                            $set('variant_id', $variants->first()->id);
                        } else {
                            // Si hay más de una o ninguna → limpiar selección
                            $set('variant_id', null);
                        }
                    }),

                Select::make('variant_id')
                    ->label('Variante')
                    ->options(function (callable $get) {
                        $productId = $get('product_id');

                        if (!$productId) {
                            return [];
                        }

                        return \App\Models\Variant::where('product_id', $productId)
                            ->where('status', 'activo')
                            ->get()
                            ->pluck('full_name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required(),







                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Productos de la Promoción')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
