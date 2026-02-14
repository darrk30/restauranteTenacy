<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\CashRegisterResource\Pages;
use App\Filament\Restaurants\Resources\CashRegisterResource\RelationManagers;
use App\Filament\Restaurants\Resources\CashRegisterResource\RelationManagers\UsersRelationManager;
use App\Models\CashRegister;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Cajas Registradoras';

    protected static ?string $pluralModelLabel = 'Cajas Registradoras';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->required()
                    ->maxLength(255),

                Toggle::make('status')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->sortable()->searchable(),
                Tables\Columns\IconColumn::make('status')->label('Active')->sortable()->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashRegisters::route('/'),
            'create' => Pages\CreateCashRegister::route('/create'),
            'edit' => Pages\EditCashRegister::route('/{record}/edit'),
        ];
    }
}
