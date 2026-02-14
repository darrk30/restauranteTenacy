<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Restaurants\Resources\PrinterResource\Pages\ListPrinters;
use App\Filament\Restaurants\Resources\PrinterResource\Pages;
use App\Models\Printer;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';


    // protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Impresoras';

    protected static ?string $pluralModelLabel = 'Impresoras';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('description'),
                Toggle::make('status')
                    ->required()
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->placeholder('Ninguna')
                    ->searchable(),
                IconColumn::make('status')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
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
            'index' => ListPrinters::route('/'),
            // 'create' => Pages\CreatePrinter::route('/create'),
            // 'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
