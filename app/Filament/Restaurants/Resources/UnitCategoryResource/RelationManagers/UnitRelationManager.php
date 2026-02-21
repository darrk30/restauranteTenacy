<?php

namespace App\Filament\Restaurants\Resources\UnitCategoryResource\RelationManagers;

use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UnitRelationManager extends RelationManager
{
    protected static string $relationship = 'units';
    protected static ?string $title = 'Unidades de esta categoría';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->maxLength(50)
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->required(),

                // ⚖️ Unidad base (solo unidades de la misma categoría que son base o sin referencia)
                Forms\Components\Select::make('reference_unit_id')
                    ->label('Unidad base')
                    ->relationship('unidadBase', 'name')
                    ->searchable()
                    ->preload()
                    ->options(function ($livewire, ?Unit $record) {
                        $category = $livewire->ownerRecord;
                        if (! $category) {
                            return [];
                        }

                        return Unit::query()
                            ->where('unit_category_id', $category->id)
                            // Evita que se seleccione a sí misma al editar
                            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                            ->pluck('name', 'id');
                    })
                    ->helperText('Selecciona una unidad base compatible con esta categoría.')
                    ->reactive(),

                Forms\Components\Toggle::make('is_base')
                    ->label('¿Es unidad base?')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre'),
                Tables\Columns\TextColumn::make('code')->label('Código'),
                Tables\Columns\TextColumn::make('quantity')->label('Cantidad'),
                Tables\Columns\TextColumn::make('unidadBase.name')->label('Unidad base'),
                Tables\Columns\IconColumn::make('is_base')
                    ->boolean()
                    ->label('Es base'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nueva')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Crear nueva unidad')
                    ->modalSubmitActionLabel('Crear Unidad'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
