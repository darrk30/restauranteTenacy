<?php

namespace App\Filament\Restaurants\Resources\CashRegisterResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\AttachAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre'), // Etiqueta de la columna
                TextColumn::make('email')
                    ->label('Correo Electrónico'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Botón para asignar (Attach)
                AttachAction::make()
                    ->label('Asignar usuario')
                    ->modalHeading('Asignar usuario a la caja')
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                // Botón editar
                EditAction::make()
                    ->label('Editar'), 
                
                // Botón desvincular (Detach)
                DetachAction::make()
                    ->label('Desvincular'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Botón desvincular varios
                    DetachBulkAction::make()
                        ->label('Desvincular seleccionados'),
                ]),
            ]);
    }
}