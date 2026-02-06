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
                //  ESCENARIO 1: APERTURA (Solo en Create)
                // ==========================================
                Grid::make(3)
                    ->visible(fn(string $operation) => $operation === 'create')
                    ->schema([
                        Section::make('DETALLE DE APERTURA')
                            ->columnSpan(2)
                            ->schema([
                                Select::make('cash_register_id')
                                    ->label('CAJA')
                                    ->relationship(
                                        name: 'cashRegister',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function ($query) {
                                            // CORRECCIÓN AQUÍ: Usamos 'users.id' en lugar de solo 'id'
                                            $query->whereHas('users', function ($q) {
                                                $q->where('users.id', Auth::id());
                                            });

                                            // Evitar mostrar cajas que ya están abiertas
                                            $query->whereDoesntHave('sesionCashRegisters', function ($q) {
                                                $q->where('status', 'open');
                                            });

                                            return $query;
                                        }
                                    )
                                    ->required()
                                    ->preload()
                                    // CORRECCIÓN AQUÍ TAMBIÉN: 'users.id'
                                    ->disabled(fn() => \App\Models\CashRegister::whereHas('users', fn($q) => $q->where('users.id', Auth::id()))->count() === 0),

                                TextInput::make('turno_ficticio')
                                    ->label('TURNO')
                                    ->default('Mañana')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('opening_amount')
                                    ->label('MONTO APERTURA')
                                    ->prefix('S/')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                            ]),

                        Section::make('INFO DE LA SESIÓN')
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('cajero_name')
                                    ->label('CAJERO')
                                    ->default(fn() => Auth::user()->name)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('status_display')
                                    ->label('ESTADO')
                                    ->default('POR APERTURAR')
                                    ->extraInputAttributes(['style' => 'color: green; font-weight: bold;'])
                                    ->disabled()
                                    ->dehydrated(false),

                                DateTimePicker::make('opened_at')
                                    ->label('FECHA DE APERTURA')
                                    ->default(now())
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                    ]),

                // ==========================================
                //  ESCENARIO 2: CIERRE DETALLADO (Solo en Edit)
                // ==========================================
                Grid::make(1)
                    ->visible(fn(string $operation) => in_array($operation, ['edit', 'view']))
                    ->schema([

                        // 🟥 SECCIÓN SUPERIOR: DETALLE POR MÉTODO DE PAGO
                        Section::make('ARQUEO POR MÉTODO DE PAGO')
                            ->schema([

                                // 1. MENSAJE: Si no hay detalles generados
                                Placeholder::make('no_details')
                                    ->label('')
                                    ->content('⚠️ No se han generado detalles de cierre. Verifique que se hayan registrado movimientos.')
                                    ->visible(fn($record) => !$record || $record->cierreCajaDetalles()->doesntExist()),

                                // 2. TABLA: Repeater vinculado a la relación 'cierreCajaDetalles'
                                Repeater::make('cierreCajaDetalles')
                                    ->label('')
                                    ->relationship('cierreCajaDetalles') // 🔥 Vinculación directa a la BD
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columns(4)
                                    ->visible(fn($record) => $record && $record->cierreCajaDetalles()->exists())
                                    ->schema([

                                        Hidden::make('metodo_pago_id'),
                                        // A. Método de Pago (Select deshabilitado para mostrar nombre)
                                        TextInput::make('nombre_metodo_visual')
                                            ->label('MÉTODO')
                                            ->disabled()
                                            ->dehydrated(false) // No intentamos guardar esto en la BD
                                            // 🔥 TRUCO: Obtenemos el nombre a través de la relación
                                            ->formatStateUsing(function ($record) {
                                                // $record es la fila de 'cierre_caja_detalles'
                                                return $record->paymentMethod->name ?? 'Sin Nombre';
                                            }), // Enviamos el ID al guardar

                                        // B. Monto Sistema (Calculado por el Observer previamente)
                                        TextInput::make('monto_sistema')
                                            ->label('SISTEMA')
                                            ->prefix('S/')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(), // Se guarda lo que diga el sistema

                                        // C. Monto Cajero (Editable)
                                        TextInput::make('monto_cajero')
                                            ->label('CAJERO (REAL)')
                                            ->prefix('S/')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            // 🔥 LÓGICA DE CÁLCULO EN TIEMPO REAL
                                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                // 1. Calcular diferencia de la fila actual
                                                $sistema = (float) $get('monto_sistema');
                                                $cajero = (float) $state;
                                                $set('diferencia', $cajero - $sistema);

                                                // 2. Calcular Totales Globales (Salimos del repeater con ../../)
                                                $items = $get('../../cierreCajaDetalles');

                                                // Usamos collect para sumar fácil
                                                $totalCajero = collect($items)->sum('monto_cajero');
                                                $totalSistema = collect($items)->sum('monto_sistema');

                                                $set('../../cajero_closing_amount', $totalCajero);
                                                $set('../../system_closing_amount', $totalSistema);
                                                $set('../../difference', $totalCajero - $totalSistema);
                                            }),

                                        // D. Diferencia (Visual)
                                        TextInput::make('diferencia')
                                            ->label('DIFERENCIA')
                                            ->prefix('S/')
                                            ->disabled()
                                            ->dehydrated() // Guardamos la diferencia
                                            ->extraInputAttributes(fn($state) => [
                                                'style' => 'font-weight: bold; color: ' . ($state < 0 ? 'red' : 'green')
                                            ]),
                                    ]),
                            ]),

                        // 🟧 SECCIÓN INFERIOR: TOTALES Y SIDEBAR
                        Grid::make(3)
                            ->schema([
                                // COLUMNA IZQUIERDA (2/3): TOTALES
                                Section::make('TOTALES GENERALES')
                                    ->columnSpan(2)
                                    ->schema([
                                        TextInput::make('cajero_closing_amount')
                                            ->label('TOTAL CIERRE CAJERO')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->extraInputAttributes(['style' => 'font-weight: bold; font-size: 1.1em; color: #d97706;']),

                                        TextInput::make('system_closing_amount')
                                            ->label('TOTAL SISTEMA')
                                            ->prefix('S/')
                                            ->readOnly()
                                            // Cargar valor inicial al abrir la página
                                            ->afterStateHydrated(function (TextInput $component, $record) {
                                                if ($record) {
                                                    // Sumamos directamente de la tabla detalles_cierre
                                                    $total = $record->cierreCajaDetalles()->sum('monto_sistema');
                                                    $component->state($total);
                                                }
                                            }),

                                        TextInput::make('difference')
                                            ->label('DIFERENCIA')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->extraInputAttributes(fn($state) => [
                                                'style' => 'font-weight: bold; color: ' . ($state < 0 ? 'red' : 'green')
                                            ]),

                                        Textarea::make('notes')
                                            ->label('NOTAS')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                                // 🟦 COLUMNA DERECHA (1/3): INFO SIDEBAR
                                Section::make('INFO')
                                    ->columnSpan(1)
                                    ->schema([
                                        TextInput::make('cajero_name_display')
                                            ->label('CAJERO')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(fn($record) => $record?->user?->name ?? Auth::user()->name),

                                        Select::make('status')
                                            ->label('ESTADO')
                                            ->options([
                                                'open' => 'Abierta',
                                                'closed' => 'Cerrada',
                                            ])
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(fn() => 'closed'),

                                        DateTimePicker::make('closed_at')
                                            ->label('FECHA CIERRE')
                                            ->required()
                                            ->formatStateUsing(fn($state) => $state ?? now()),
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

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cajero')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('cashRegister.name')
                    ->label('Caja')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

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

                // 1. FECHA DE APERTURA (Dos líneas)
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('F. Apertura')
                    ->dateTime('d/m/Y') // Arriba: Solo la fecha
                    ->description(fn($record) => $record->opened_at?->format('h:i A')) // Abajo: La hora (ej: 10:30 AM)
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->iconColor('primary'),

                // 2. FECHA DE CIERRE (Dos líneas)
                Tables\Columns\TextColumn::make('closed_at')
                    ->label('F. Cierre')
                    ->dateTime('d/m/Y') // Arriba: Solo la fecha
                    ->description(
                        fn($record) => $record->closed_at
                            ? $record->closed_at->format('h:i A') // Si cerró: Muestra la hora
                            : 'En curso...' // Si no: Muestra texto
                    )
                    ->sortable()
                    ->placeholder('En curso...') // Texto para la línea de arriba si es null
                    ->icon(fn($state) => $state ? 'heroicon-m-lock-closed' : 'heroicon-m-clock')
                    ->color(fn($state) => $state ? 'gray' : 'warning'),

                Tables\Columns\TextColumn::make('difference')
                    ->label('Cuadre')
                    ->money('PEN')
                    ->placeholder('-')
                    ->color(fn($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray')),
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
                // BOTÓN 1: CERRAR CAJA (Solo visible si está ABIERTA)
                Tables\Actions\EditAction::make()
                    ->label('Cerrar Caja')
                    ->icon('heroicon-m-lock-closed')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'open'),

                // BOTÓN 2: VER DETALLES (Solo visible si está CERRADA)
                Tables\Actions\ViewAction::make() // 🔥 Usamos ViewAction
                    ->label('Ver Detalles')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->visible(fn($record) => $record->status === 'closed'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSessionCashRegisters::route('/'),
            'create' => Pages\CreateSessionCashRegister::route('/create'),
            'edit' => Pages\EditSessionCashRegister::route('/{record}/edit'),
            'view' => Pages\ViewSessionCashRegister::route('/{record}'),
        ];
    }
}
