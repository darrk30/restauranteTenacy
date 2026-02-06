<?php

namespace App\Filament\Restaurants\Resources\CashRegisterResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre'), // Etiqueta de la columna
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Botón para asignar (Attach)
                Tables\Actions\AttachAction::make()
                    ->label('Asignar usuario')
                    ->modalHeading('Asignar usuario a la caja')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                // Botón editar
                Tables\Actions\EditAction::make()
                    ->label('Editar'), 
                
                // Botón desvincular (Detach)
                Tables\Actions\DetachAction::make()
                    ->label('Desvincular'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Botón desvincular varios
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Desvincular seleccionados'),
                ]),
            ]);
    }
}