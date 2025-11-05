<?php

namespace App\Filament\Restaurants\Resources\FloorResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'Tables';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre de Mesa')
                    ->required(),
                Forms\Components\Toggle::make('status')
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
                Tables\Columns\TextColumn::make('name')->label('Mesa'),
                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->label('Disponible'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Creado'),
            ])
            ->filters([
                //
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
