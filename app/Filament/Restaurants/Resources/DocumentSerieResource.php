<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\DocumentSeriesType; // Importamos tu Enum
use App\Filament\Restaurants\Resources\DocumentSerieResource\Pages;
use App\Models\DocumentSerie;
use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Validation\Rules\Unique;

class DocumentSerieResource extends Resource
{
    protected static ?string $model = DocumentSerie::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationLabel = 'Series de Documentos';
    protected static ?string $modelLabel = 'Serie';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 115;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->unique(
                                table: 'document_series',
                                column: 'serie',
                                ignorable: fn($record) => $record,
                                modifyRuleUsing: function (Unique $rule) {
                                    return $rule->where('restaurant_id', Filament::getTenant()->id);
                                }
                            )
                            ->validationMessages([
                                'unique' => 'Esta serie ya ha sido registrada para este restaurante.',
                            ]),

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
                Tables\Filters\SelectFilter::make('type_documento')
                    ->label('Tipo de Documento')
                    ->options(DocumentSeriesType::class), // 🔥 Filtro automático con Enum
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, DocumentSerie $record) {
                        // 🟢 Validamos si existen ventas con esta serie para este restaurante
                        $tieneVentas = Sale::where('serie', $record->serie)
                            ->where('restaurant_id', Filament::getTenant()->id)
                            ->exists();

                        if ($tieneVentas) {
                            // Notificación de advertencia personalizada
                            Notification::make()
                                ->warning()
                                ->title('Acción no permitida')
                                ->body("La serie **{$record->serie}** tiene ventas asociadas y no puede eliminarse.")
                                ->persistent()
                                ->actions([
                                    // 🟢 Botón de desactivar dentro de la notificación
                                    \Filament\Notifications\Actions\Action::make('desactivar')
                                        ->label('Desactivar Serie')
                                        ->color('warning')
                                        ->button()
                                        ->close()
                                        ->action(function () use ($record) {
                                            $record->update(['is_active' => false]);

                                            Notification::make()
                                                ->success()
                                                ->title('Serie desactivada')
                                                ->send();
                                        }),
                                ])
                                ->send();

                            // 🛑 Detiene la ejecución del borrado
                            $action->cancel();
                        }
                    }),

                // Opcional: Agregar una acción de desactivar directa en la fila
                Tables\Actions\Action::make('desactivar_fila')
                    ->label('Desactivar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->hidden(fn(DocumentSerie $record) => !$record->is_active)
                    ->requiresConfirmation()
                    ->action(fn(DocumentSerie $record) => $record->update(['is_active' => false])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentSeries::route('/'),
            // 'create' => Pages\CreateDocumentSerie::route('/create'),
            // 'edit' => Pages\EditDocumentSerie::route('/{record}/edit'),
        ];
    }
}
