<?php

namespace App\Filament\Restaurants\Resources\FloorResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'Tables';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de Mesa')
                    ->required(),
                TextInput::make('asientos')
                    ->label('Número de Asientos')
                    ->default(1),
                Toggle::make('status')
                    ->label('Disponible')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Pisos y Mesas')
            ->columns([
                TextColumn::make('name')->label('Mesa'),
                TextColumn::make('asientos')->label('Asientos'),
                IconColumn::make('status')->boolean()->label('Disponible'),
                TextColumn::make('created_at')->dateTime()->label('Creado'),
            ])
            ->filters([
                //
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
