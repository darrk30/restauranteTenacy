<?php

namespace App\Filament\Restaurants\Resources\UnitCategoryResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\Unit;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UnitRelationManager extends RelationManager
{
    protected static string $relationship = 'units'; // ← asegúrate que coincide con la relación real

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Código')
                    ->maxLength(50)
                    ->required(),

                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->required(),

                // ⚖️ Unidad base (solo unidades de la misma categoría que son base o sin referencia)
                Select::make('reference_unit_id')
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

                Toggle::make('is_base')
                    ->label('¿Es unidad base?')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('code')->label('Código'),
                TextColumn::make('quantity')->label('Cantidad'),
                TextColumn::make('unidadBase.name')->label('Unidad base'),
                IconColumn::make('is_base')
                    ->boolean()
                    ->label('Es base'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
