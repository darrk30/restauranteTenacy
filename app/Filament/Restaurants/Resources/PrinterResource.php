<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\PrinterResource\Pages;
use App\Models\Printer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 110;

    protected static ?string $navigationLabel = 'Impresoras';

    protected static ?string $pluralModelLabel = 'Impresoras';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('description'),
                Forms\Components\Toggle::make('status')
                    ->required()
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->sortable()
                    ->placeholder('Ninguna')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar Impresora') 
                    ->modalSubmitActionLabel('Actualizar Impresora'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPrinters::route('/'),
            // 'create' => Pages\CreatePrinter::route('/create'),
            // 'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
