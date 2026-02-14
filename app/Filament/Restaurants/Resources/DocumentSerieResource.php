<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\BulkActionGroup;
use App\Filament\Restaurants\Resources\DocumentSerieResource\Pages\ListDocumentSeries;
use App\Filament\Restaurants\Resources\DocumentSerieResource\Pages\CreateDocumentSerie;
use App\Filament\Restaurants\Resources\DocumentSerieResource\Pages\EditDocumentSerie;
use App\Enums\DocumentSeriesType; // Importamos tu Enum
use App\Filament\Restaurants\Resources\DocumentSerieResource\Pages;
use App\Models\DocumentSerie;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class DocumentSerieResource extends Resource
{
    protected static ?string $model = DocumentSerie::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Series de Documentos';
    protected static ?string $modelLabel = 'Serie';
    // protected static ?string $navigationGroup = 'Configuración';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración de Comprobantes')
                    ->description('Administra las series y correlativos para la facturación.')
                    ->schema([
                        Select::make('type_documento')
                            ->label('Tipo de Documento')
                            ->options(DocumentSeriesType::class) // 🔥 Usa el Enum directamente
                            ->required()
                            ->native(false),

                        TextInput::make('serie')
                            ->label('Serie')
                            ->placeholder('Ej: F001, B001')
                            ->required()
                            ->maxLength(4)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        TextInput::make('current_number')
                            ->label('Correlativo Actual')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Indica el último número emitido.'),

                        Toggle::make('is_active')
                            ->label('Serie Activa')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type_documento')
                    ->label('Tipo')
                    ->badge()
                    // Aplicamos colores basados en el valor del Enum
                    ->color(fn($state): string => match ($state) {
                        DocumentSeriesType::FACTURA => 'info',
                        DocumentSeriesType::BOLETA => 'success',
                        DocumentSeriesType::NOTA_CREDITO => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('serie')
                    ->label('Serie')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('current_number')
                    ->label('N° Correlativo')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type_documento')
                    ->label('Tipo de Documento')
                    ->options(DocumentSeriesType::class), // 🔥 Filtro automático con Enum
            ])
            ->recordActions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentSeries::route('/'),
            'create' => CreateDocumentSerie::route('/create'),
            'edit' => EditDocumentSerie::route('/{record}/edit'),
        ];
    }
}
