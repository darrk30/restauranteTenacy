<?php

namespace App\Filament\Restaurants\Resources;

use App\Enums\TipoEgreso;
use App\Filament\Restaurants\Resources\ConceptoCajasResource\Pages;
use App\Models\ConceptoCaja;
use App\Models\SessionCashRegister;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker; // Importante para el filtro
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder; // Importante para el filtro

class ConceptoCajasResource extends Resource
{
    protected static ?string $model = ConceptoCaja::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Ingresos y Egresos';
    protected static ?string $modelLabel = 'Ingresos y Egresos';
    protected static ?string $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 4;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y h:i A')
                    ->label('Fecha')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_movimiento')
                    ->badge()
                    ->colors([
                        'success' => 'ingreso',
                        'danger' => 'egreso',
                    ])
                    ->formatStateUsing(fn(string $state) => ucfirst($state))
                    ->label('Tipo'),

                Tables\Columns\TextColumn::make('categoria')
                    ->badge()
                    ->label('Categoría')
                    ->formatStateUsing(fn($state) => $state instanceof TipoEgreso ? $state->getLabel() : 'Ingreso General')
                    ->color(fn($state) => $state instanceof TipoEgreso ? $state->getColor() : 'gray'),

                Tables\Columns\TextColumn::make('persona_externa')
                    ->label('Responsable / Entidad')
                    ->placeholder('Sin dato')
                    ->formatStateUsing(function ($record) {
                        return $record->personal_id
                            ? $record->personal->name . ' (Personal)'
                            : $record->persona_externa;
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo / Detalle')
                    ->placeholder('Sin dato')
                    ->limit(30)
                    ->tooltip(fn(Tables\Columns\TextColumn $column): ?string => $column->getState()),

                Tables\Columns\TextColumn::make('monto')
                    ->prefix('S/ ')
                    ->numeric(decimalPlaces: 2)
                    ->label('Monto')
                    ->weight('bold')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->colors([
                        'success' => 'aprobado',
                        'danger' => 'anulado',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DateTimePicker::make('fecha_desde')
                        ->label('Desde')
                        ->native(false)
                        ->displayFormat('d/m/Y h:i A')
                        ->format('Y-m-d H:i:s')
                        ->seconds(false)
                        ->default($sesionAbierta ? $sesionAbierta->opened_at : now()->startOfDay())
                        ->live(),

                    DateTimePicker::make('fecha_hasta')
                        ->label('Hasta')
                        ->native(false)
                        ->displayFormat('d/m/Y h:i A')
                        ->format('Y-m-d H:i:s')
                        ->seconds(false)
                        ->default(now())
                        ->live(),
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
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_desde'])->format('d/m/Y h:i A');
                        }
                        if ($data['fecha_hasta'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_hasta'])->format('d/m/Y h:i A');
                        }
                        return $indicators;
                    }),
            ])
            // 3. ACCIONES
            ->actions([
                Tables\Actions\Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anular Movimiento')
                    ->modalDescription('¿Estás seguro de anular este movimiento? El monto se revertirá de la caja.')
                    ->modalSubmitActionLabel('Sí, Anular')
                    ->visible(function ($record) use ($sesionAbierta) {
                        if ($record->estado !== 'aprobado' || $sesionAbierta === null || $record->session_cash_register_id !== $sesionAbierta->id) {
                            return false;
                        }
                        $user = Auth::user();
                        if ($user->hasRole('Super Admin')) return true;

                        $sufijo = filament()->getTenant() ? '_rest' : '_admin';
                        try {
                            return $user->hasPermissionTo('anular_ingreso_egreso' . $sufijo);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
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
                Tables\Actions\CreateAction::make('ingreso')
                    ->label('Registrar Ingreso')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->modalHeading('Nuevo Ingreso a Caja')
                    ->visible(function () use ($sesionAbierta) {
                        if ($sesionAbierta === null) return false;

                        $user = Auth::user();
                        if ($user->hasRole('Super Admin')) return true;

                        $sufijo = filament()->getTenant() ? '_rest' : '_admin';
                        try {
                            return $user->hasPermissionTo('registrar_ingreso' . $sufijo);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->form([
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
                    ->mutateFormDataUsing(function (array $data) use ($sesionAbierta) {
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
                Tables\Actions\CreateAction::make('egreso')
                    ->label('Registrar Egreso')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->modalHeading('Registrar Salida de Dinero')
                    ->visible(function () use ($sesionAbierta) {
                        if ($sesionAbierta === null) return false;

                        $user = Auth::user();
                        if ($user->hasRole('Super Admin')) return true;

                        $sufijo = filament()->getTenant() ? '_rest' : '_admin';
                        try {
                            return $user->hasPermissionTo('registrar_egreso' . $sufijo);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->form([
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
                    ->mutateFormDataUsing(function (array $data) use ($sesionAbierta) {
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
            'index' => Pages\ListConceptoCajas::route('/'),
        ];
    }
}
