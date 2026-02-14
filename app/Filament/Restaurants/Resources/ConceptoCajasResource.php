<?php

namespace App\Filament\Restaurants\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Restaurants\Resources\ConceptoCajasResource\Pages\ListConceptoCajas;
use App\Enums\TipoEgreso;
use App\Filament\Restaurants\Resources\ConceptoCajasResource\Pages;
use App\Models\ConceptoCaja;
use App\Models\SessionCashRegister;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker; // Importante para el filtro
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder; // Importante para el filtro

class ConceptoCajasResource extends Resource
{
    protected static ?string $model = ConceptoCaja::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Ingresos y Egresos';
    protected static ?string $modelLabel = 'Ingresos y Egresos';


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tipo_movimiento')->disabled(),
                TextInput::make('monto')->prefix('S/')->disabled(),
                Textarea::make('motivo')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        // 1. Buscamos la sesión ABIERTA del usuario actual
        $sesionAbierta = SessionCashRegister::where('user_id', Auth::id())
            ->whereNull('closed_at')
            ->first();

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y h:i A')
                    ->label('Fecha')
                    ->sortable(),

                TextColumn::make('tipo_movimiento')
                    ->badge()
                    ->colors([
                        'success' => 'ingreso',
                        'danger' => 'egreso',
                    ])
                    ->formatStateUsing(fn(string $state) => ucfirst($state))
                    ->label('Tipo'),

                TextColumn::make('categoria')
                    ->badge()
                    ->label('Categoría')
                    ->formatStateUsing(fn($state) => $state instanceof TipoEgreso ? $state->getLabel() : 'Ingreso General')
                    ->color(fn($state) => $state instanceof TipoEgreso ? $state->getColor() : 'gray'),

                TextColumn::make('persona_externa')
                    ->label('Responsable / Entidad')
                    ->placeholder('Sin dato')
                    ->formatStateUsing(function ($record) {
                        return $record->personal_id
                            ? $record->personal->name . ' (Personal)'
                            : $record->persona_externa;
                    })
                    ->wrap(),

                TextColumn::make('motivo')
                    ->label('Motivo / Detalle')
                    ->placeholder('Sin dato')
                    ->limit(30)
                    ->tooltip(fn(TextColumn $column): ?string => $column->getState()),

                TextColumn::make('monto')
                    ->prefix('S/ ')
                    ->numeric(decimalPlaces: 2)
                    ->label('Monto')
                    ->weight('bold')
                    ->alignRight(),

                TextColumn::make('estado')
                    ->badge()
                    ->colors([
                        'success' => 'aprobado',
                        'danger' => 'anulado',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DatetimePicker::make('fecha_desde')->label('Desde')->hourMode(12)->displayFormat('d/m/y h:i A')->seconds(false)->default($sesionAbierta ? $sesionAbierta->opened_at : now()->startOfDay()),
                        DatetimePicker::make('fecha_hasta')->label('Hasta')->hourMode(12)->displayFormat('d/m/y h:i A')->seconds(false)->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'],
                                fn(Builder $query, $date) => $query->where('created_at', '>=', $date),
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn(Builder $query, $date) => $query->where('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        // Mostramos formato amigable incluyendo la hora
                        if ($data['fecha_desde'] ?? null) {
                            $indicators[] = 'Desde: ' . Carbon::parse($data['fecha_desde'])->format('d/m/Y h:i A');
                        }
                        if ($data['fecha_hasta'] ?? null) {
                            $indicators[] = 'Hasta: ' . Carbon::parse($data['fecha_hasta'])->format('d/m/Y h:i A');
                        }
                        return $indicators;
                    }),
            ])
            // 3. ACCIONES
            ->recordActions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Movimiento')
                    ->modalDescription('¿Estás seguro de anular este movimiento? El monto se revertirá de la caja.')
                    ->modalSubmitActionLabel('Sí, Anular')

                    // 🔥 CONDICIÓN ESTRICTA PARA MOSTRAR BOTÓN:
                    // 1. El estado debe ser 'aprobado'.
                    // 2. Debe existir una sesión abierta ($sesionAbierta !== null).
                    // 3. El movimiento debe pertenecer a ESA sesión abierta ($record->session_cash_register_id === $sesionAbierta->id).
                    ->visible(
                        fn($record) =>
                        $record->estado === 'aprobado' &&
                            $sesionAbierta !== null &&
                            $record->session_cash_register_id === $sesionAbierta->id
                    )
                    ->action(function ($record) {
                        $record->update(['estado' => 'anulado']);

                        Notification::make()
                            ->title('Movimiento Anulado')
                            ->success()
                            ->send();
                    }),
            ])

            // 4. CABECERA (Crear)
            ->headerActions([

                // 🟢 INGRESO
                CreateAction::make('ingreso')
                    ->label('Registrar Ingreso')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->modalHeading('Nuevo Ingreso a Caja')
                    ->visible(fn() => $sesionAbierta !== null)
                    ->schema([
                        TextInput::make('persona_externa')
                            ->label('Recibido De')
                            ->placeholder('Ej: Cliente Juan, Socio')
                            ->required(),

                        TextInput::make('monto')
                            ->label('Monto')
                            ->prefix('S/')
                            ->numeric()
                            ->required(),

                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->required(),
                    ])
                    ->mutateDataUsing(function (array $data) use ($sesionAbierta) {
                        if (filament()->getTenant()) {
                            $data['restaurant_id'] = filament()->getTenant()->id;
                        }
                        $data['session_cash_register_id'] = $sesionAbierta->id;
                        $data['usuario_id'] = Auth::id();
                        $data['tipo_movimiento'] = 'ingreso';
                        $data['estado'] = 'aprobado';
                        return $data;
                    }),

                // 🔴 EGRESO
                CreateAction::make('egreso')
                    ->label('Registrar Egreso')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->modalHeading('Registrar Salida de Dinero')
                    ->visible(fn() => $sesionAbierta !== null)
                    ->schema([
                        Grid::make(1)->schema([
                            Select::make('categoria')
                                ->label('Categoría')
                                ->options(TipoEgreso::class)
                                ->required()
                                ->live(),

                            Select::make('personal_id')
                                ->label('Seleccionar Personal')
                                ->relationship('personal', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->visible(fn(Get $get) => $get('categoria') === TipoEgreso::REMUNERACION->value),

                            TextInput::make('persona_externa')
                                ->label('Entregado A')
                                ->placeholder('Ej: Proveedor Gas, Tienda X')
                                ->required()
                                ->visible(
                                    fn(Get $get) =>
                                    $get('categoria') !== null &&
                                        $get('categoria') !== TipoEgreso::REMUNERACION->value
                                ),

                            TextInput::make('monto')
                                ->label('Monto')
                                ->prefix('S/')
                                ->numeric()
                                ->required(),

                            Textarea::make('motivo')
                                ->label('Motivo / Detalle')
                                ->required(),
                        ])
                    ])
                    ->mutateDataUsing(function (array $data) use ($sesionAbierta) {
                        if (filament()->getTenant()) {
                            $data['restaurant_id'] = filament()->getTenant()->id;
                        }
                        $data['session_cash_register_id'] = $sesionAbierta->id;
                        $data['usuario_id'] = Auth::id();
                        $data['tipo_movimiento'] = 'egreso';
                        $data['estado'] = 'aprobado';
                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConceptoCajas::route('/'),
        ];
    }
}
