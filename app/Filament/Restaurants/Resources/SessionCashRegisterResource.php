<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\SessionCashRegisterResource\Pages;
use App\Models\SessionCashRegister;
use App\Models\PaymentMethod;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SessionCashRegisterResource extends Resource
{
    protected static ?string $model = SessionCashRegister::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Apertura y Cierre';
    protected static ?string $pluralModelLabel = 'Apertura y Cierre';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ==========================================
                // ESCENARIO 1: APERTURA (Solo en Create)
                // ==========================================
                Grid::make(['default' => 1, 'lg' => 3])
                    ->visible(fn(string $operation) => $operation === 'create')
                    ->schema([
                        Section::make('DETALLE DE APERTURA')
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                Select::make('cash_register_id')
                                    ->label('CAJA')
                                    ->relationship(
                                        name: 'cashRegister',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn($query) => $query
                                            ->whereHas('users', fn($q) => $q->where('users.id', Auth::id()))
                                            ->whereDoesntHave('sesionCashRegisters', fn($q) => $q->where('status', 'open'))
                                    )
                                    ->required()
                                    ->preload(),
                                TextInput::make('turno_ficticio')->label('TURNO')->default('Mañana')->disabled()->dehydrated(false),
                                TextInput::make('opening_amount')->label('MONTO APERTURA')->prefix('S/')->numeric()->default(0)->required(),
                            ]),
                        Section::make('INFO DE LA SESIÓN')
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextInput::make('cajero_name')->label('CAJERO')->default(fn() => Auth::user()->name)->disabled()->dehydrated(false),
                                TextInput::make('status_display')->label('ESTADO')->default('POR APERTURAR')->extraInputAttributes(['style' => 'color: green; font-weight: bold;'])->disabled()->dehydrated(false),
                                DateTimePicker::make('opened_at')->label('FECHA DE APERTURA')->default(now())->disabled()->dehydrated(),
                            ]),
                    ]),

                // ==========================================
                // ESCENARIO 2: CIERRE DETALLADO (Edit / View)
                // ==========================================
                Grid::make(1)
                    ->visible(fn(string $operation) => in_array($operation, ['edit', 'view']))
                    ->schema([
                        Section::make('ARQUEO POR MÉTODO DE PAGO')
                            ->description('Detalle de ingresos registrados vs. conteo físico')
                            ->schema([
                                Placeholder::make('no_details')
                                    ->label('')
                                    ->content('⚠️ No hay movimientos registrados para esta sesión.')
                                    ->visible(fn($record) => !$record || $record->cierreCajaDetalles()->doesntExist()),

                                Repeater::make('cierreCajaDetalles')
                                    ->label('')
                                    ->relationship('cierreCajaDetalles')
                                    ->loadStateFromRelationshipsUsing(function ($component, $record, string $operation) {
                                        if (!$record) return;
                                        $query = $record->cierreCajaDetalles()->with('paymentMethod');
                                        if ($operation === 'view') $query->where('monto_sistema', '!=', 0);
                                        $items = $query->get();

                                        if ($items->isEmpty() && $operation !== 'view') {
                                            $datosGenerados = \App\Models\PaymentMethod::where('status', true)->get()->map(function ($metodo) use ($record) {
                                                $total = $record->cashRegisterMovements()->where('payment_method_id', $metodo->id)->where('tipo', 'Ingreso')->where('status', 'aprobado')->sum('monto') -
                                                    $record->cashRegisterMovements()->where('payment_method_id', $metodo->id)->where('tipo', 'Salida')->where('status', 'aprobado')->sum('monto');
                                                return [
                                                    'metodo_pago_id' => $metodo->id,
                                                    'nombre_metodo_visual' => $metodo->name,
                                                    'monto_sistema' => $total,
                                                    'monto_cajero' => 0,
                                                    'diferencia' => -$total,
                                                ];
                                            })->toArray();
                                            $component->state($datosGenerados);
                                            return;
                                        }
                                        $component->state($items->map(function ($item) {
                                            $fila = $item->toArray();
                                            $fila['nombre_metodo_visual'] = $item->paymentMethod->name ?? 'Desconocido';
                                            return $fila;
                                        })->toArray());
                                    })
                                    ->addable(false)->deletable(false)->reorderable(false)
                                    ->columns(['default' => 2, 'sm' => 2, 'lg' => 4])
                                    ->schema([
                                        Hidden::make('metodo_pago_id'),
                                        TextInput::make('nombre_metodo_visual')->label('MÉTODO')->disabled()->dehydrated(false),
                                        TextInput::make('monto_sistema')->label('SISTEMA')->prefix('S/')->numeric()->disabled()->dehydrated(),
                                        TextInput::make('monto_cajero')->label('CAJERO (REAL)')->prefix('S/')->numeric()->default(0)->required()
                                            ->live(onBlur: true)->disabled(fn(string $operation) => $operation === 'view')
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                $sistema = (float) $get('monto_sistema');
                                                $cajero = (float) $state;
                                                $set('diferencia', $cajero - $sistema);
                                                $items = $get('../../cierreCajaDetalles');
                                                $set('../../cajero_closing_amount', collect($items)->sum(fn($i) => (float)($i['monto_cajero'] ?? 0)));
                                                $set('../../system_closing_amount', collect($items)->sum(fn($i) => (float)($i['monto_sistema'] ?? 0)));
                                                $set('../../difference', (float)$get('../../cajero_closing_amount') - (float)$get('../../system_closing_amount'));
                                            }),
                                        TextInput::make('diferencia')->label('DIFERENCIA')->prefix('S/')->disabled()->dehydrated()
                                            ->extraInputAttributes(fn($state) => ['style' => 'font-weight: bold; color: ' . ($state < 0 ? 'red' : 'green')]),
                                    ]),
                            ]),

                        // 🟩 DISEÑO MEJORADO: Totales en 1 fila (PC) y 2 (Móvil)
                        Section::make('TOTALES GENERALES')
                            ->schema([
                                Grid::make(['default' => 2, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        TextInput::make('system_closing_amount')
                                            ->label('TOTAL SISTEMA')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->afterStateHydrated(fn(TextInput $component, $record) => $record ? $component->state($record->cierreCajaDetalles()->sum('monto_sistema')) : null),

                                        TextInput::make('cajero_closing_amount')
                                            ->label('TOTAL CAJERO')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->extraInputAttributes(['class' => 'font-bold text-amber-600']),

                                        TextInput::make('difference')
                                            ->label('DIFERENCIA TOTAL')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->extraInputAttributes(fn($state) => [
                                                'class' => 'font-bold ' . ($state < 0 ? 'text-danger-600' : 'text-success-600')
                                            ]),
                                    ]),
                            ]),

                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                Section::make('INFO Y NOTAS')
                                    ->schema([
                                        // En PC (lg) divide en 3 columnas, en móvil (default) se apilan en 1
                                        Grid::make(['default' => 2, 'lg' => 3])
                                            ->schema([
                                                TextInput::make('cajero_name_display')
                                                    ->label('CAJERO')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->formatStateUsing(fn($record) => $record?->user?->name ?? Auth::user()->name),

                                                Select::make('status')
                                                    ->label('ESTADO')
                                                    ->options(['open' => 'Abierta', 'closed' => 'Cerrada'])
                                                    ->disabled()
                                                    ->dehydrated(false),

                                                DateTimePicker::make('closed_at')
                                                    ->label('FECHA CIERRE')
                                                    ->required()
                                                    ->default(now()),
                                            ]),

                                        // Las notas quedan debajo ocupando todo el ancho de la sección
                                        Textarea::make('notes')
                                            ->label('NOTAS DE CIERRE')
                                            ->placeholder('Observaciones adicionales sobre el arqueo...')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('cashRegister.name')
                    ->label('Caja')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m h:i A')
                    ->sortable(),

                // 1. Total Sistema
                Tables\Columns\TextColumn::make('system_closing_amount')
                    ->label('Sistema')
                    ->money('PEN')
                    // 🔥 LÓGICA PRINCIPAL:
                    ->state(function ($record) {
                        // Validación de seguridad por si el record llega nulo
                        if (!$record) return 0;

                        // CASO 1: CAJA ABIERTA -> Calculamos en vivo
                        if ($record->status === 'open') {
                            // Sumar Ingresos (Ventas, etc.)
                            $ingresos = $record->cashRegisterMovements()
                                ->where('tipo', 'Ingreso')
                                ->where('status', '!=', 'anulado') // Ignorar anulados
                                ->sum('monto');

                            // Sumar Egresos (Gastos, Retiros)
                            $egresos = $record->cashRegisterMovements()
                                ->where('tipo', 'Salida')
                                ->where('status', '!=', 'anulado')
                                ->sum('monto');

                            // Fórmula: Apertura + Ingresos - Egresos
                            return ($record->opening_amount ?? 0) + $ingresos - $egresos;
                        }

                        // CASO 2: CAJA CERRADA -> Usamos el valor guardado en BD
                        return $record->system_closing_amount;
                    })
                    // Detalles visuales adicionales
                    ->description(fn($record) => $record?->status === 'open' ? 'En vivo' : null)
                    ->weight(fn($record) => $record?->status === 'open' ? 'bold' : 'normal')
                    ->color(fn($record) => $record?->status === 'open' ? 'primary' : 'gray'),

                // 2. Total Cajero
                Tables\Columns\TextColumn::make('cajero_closing_amount')
                    ->label('Cajero')
                    ->money('PEN')
                    ->placeholder('---')
                    ->color('warning')
                    ->weight('bold')
                    // ✅ CORRECCIÓN 3: Verificar que $record exista y no esté 'open'
                    ->visible(fn($record) => $record && $record->status !== 'open'),

                // 3. Diferencia
                Tables\Columns\TextColumn::make('difference')
                    ->label('Diferencia')
                    ->money('PEN')
                    ->placeholder('---')
                    // ✅ CORRECCIÓN 4: Verificar que $record exista
                    ->visible(fn($record) => $record && $record->status !== 'open')
                    ->color(fn(string $state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'open',
                        'danger' => 'closed',
                        'warning' => 'audit',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'open' => 'ABIERTA',
                        'closed' => 'CERRADA',
                        'audit' => 'EN ARQUEO',
                        default => $state,
                    }),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Abiertas',
                        'closed' => 'Cerradas',
                    ]),
            ])
            ->actions([
                // En acciones también es buena práctica proteger el acceso, aunque suele fallar menos aquí
                Tables\Actions\EditAction::make()
                    ->label('Cerrar Caja')
                    ->icon('heroicon-m-lock-closed')
                    ->color('warning')
                    ->visible(fn($record) => $record?->status === 'open'),

                Tables\Actions\ViewAction::make()
                    ->label('Ver Detalles')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->visible(fn($record) => $record?->status === 'closed'),
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
            'index' => Pages\ListSessionCashRegisters::route('/'),
            'create' => Pages\CreateSessionCashRegister::route('/create'),
            'edit' => Pages\EditSessionCashRegister::route('/{record}/edit'),
            'view' => Pages\ViewSessionCashRegister::route('/{record}'),
        ];
    }
}
