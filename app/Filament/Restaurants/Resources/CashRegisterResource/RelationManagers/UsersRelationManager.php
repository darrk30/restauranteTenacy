<?php

namespace App\Filament\Restaurants\Resources\CashRegisterResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';
    protected static ?string $title = 'Usuarios asignados';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
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
                    ->visible(fn() => Auth::user()->can('asignar_usuario_caja_rest')) 
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        $tenant = filament()->getTenant();
                        if ($tenant) {
                            return $query->whereHas('restaurants', function ($q) use ($tenant) {
                                $q->where('restaurants.id', $tenant->id);
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                // Botón editar
                // Tables\Actions\EditAction::make()
                //     ->visible(fn() => Auth::user()->can('editar_usuario_caja_rest'))
                //     ->label('Editar'),

                // Botón desvincular (Detach)
                Tables\Actions\DetachAction::make()
                    ->visible(fn() => Auth::user()->can('desvincular_usuario_caja_rest'))
                    ->label('Desvincular'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Botón desvincular varios
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn() => Auth::user()->can('desvincular_usuario_caja_rest'))
                        ->label('Desvincular seleccionados'),
                ]),
            ]);
    }
}
