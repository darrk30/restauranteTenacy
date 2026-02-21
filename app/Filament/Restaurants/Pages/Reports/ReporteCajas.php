<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\CashRegisterMovement;
use App\Models\SessionCashRegister;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;

class ReporteCajas extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 60;
    protected static ?string $navigationLabel = 'Aperturas y Cierres';
    protected static ?string $title = 'Reporte de Aperturas y Cierres';
    protected static string $view = 'filament.reports.cajas.reporte-cajas';

    public ?array $data = [];

    public function mount()
    {
        // 🟢 Cambiamos el valor por defecto a "mensual" para que abarque tu registro del 19 de febrero
        $this->form->fill([
            'filter_type' => 'mensual',
            'fecha_desde' => now()->subDays(30)->startOfDay()->toDateTimeString(),
            'fecha_hasta' => now()->endOfDay()->toDateTimeString(),
            'user_id' => null,
            'status' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Búsqueda')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('filter_type')
                                    ->label('Periodo')
                                    ->options([
                                        'diario' => 'Hoy',
                                        'semanal' => 'Últimos 7 días',
                                        'mensual' => 'Últimos 30 días',
                                        'personalizado' => 'Personalizado',
                                    ])
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set) {
                                        $hoy = now();
                                        if ($state === 'diario') {
                                            $set('fecha_desde', $hoy->copy()->startOfDay()->toDateTimeString());
                                            $set('fecha_hasta', $hoy->copy()->endOfDay()->toDateTimeString());
                                        } elseif ($state === 'semanal') {
                                            $set('fecha_desde', $hoy->copy()->subDays(7)->startOfDay()->toDateTimeString());
                                            $set('fecha_hasta', $hoy->copy()->endOfDay()->toDateTimeString());
                                        } elseif ($state === 'mensual') {
                                            $set('fecha_desde', $hoy->copy()->subDays(30)->startOfDay()->toDateTimeString());
                                            $set('fecha_hasta', $hoy->copy()->endOfDay()->toDateTimeString());
                                        }
                                    }),

                                DateTimePicker::make('fecha_desde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y h:i A')
                                    ->format('Y-m-d H:i:s')
                                    ->seconds(false)
                                    ->default(now()->startOfMonth())
                                    ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                                    ->live(),

                                DateTimePicker::make('fecha_hasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y h:i A')
                                    ->format('Y-m-d H:i:s')
                                    ->seconds(false)
                                    ->default(now()->endOfMonth())
                                    ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                                    ->live(),

                                Select::make('user_id')
                                    ->label('Usuario de Caja')
                                    ->options(function () {
                                        $tenant = Filament::getTenant();
                                        if (method_exists($tenant, 'users')) {
                                            return $tenant->users()->pluck('name', 'users.id');
                                        }
                                        return User::where('restaurant_id', $tenant->id)->pluck('name', 'id');
                                    })
                                    ->placeholder('Todos')
                                    ->searchable()
                                    ->live(),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'open' => 'Caja Abierta',
                                        'closed' => 'Caja Cerrada',
                                    ])
                                    ->placeholder('Todas')
                                    ->live(),
                            ]),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                // 🟢 Quitamos el where('restaurant_id') manual porque tu modelo ya tiene un GlobalScope que lo hace automáticamente.
                $query = SessionCashRegister::query()
                    ->with(['user', 'cashRegister']);

                if ($data = $this->data) {
                    if (!empty($data['fecha_desde'])) $query->where('opened_at', '>=', $data['fecha_desde']);
                    if (!empty($data['fecha_hasta'])) $query->where('opened_at', '<=', $data['fecha_hasta']);
                    if (!empty($data['user_id'])) $query->where('user_id', $data['user_id']);
                    if (!empty($data['status'])) $query->where('status', $data['status']);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('session_code')->label('Cod. Sesión')->searchable()->weight('bold'),
                TextColumn::make('cashRegister.name')->label('Caja')->default('Principal'),
                TextColumn::make('user.name')->label('Responsable')->searchable(),

                TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable()
                    ->description(fn($record) => $record->closed_at ? 'Cierre: ' . $record->closed_at->format('d/m/Y h:i A') : 'En curso'),

                TextColumn::make('system_closing_amount')
                    ->label('Total Sistema')
                    ->money('PEN')
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('difference')
                    ->label('Descuadre')
                    ->money('PEN')
                    ->color(fn($state) => (float)$state < 0 ? 'danger' : ((float)$state > 0 ? 'warning' : 'success'))
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => $state === 'open' ? 'success' : 'gray')
                    ->formatStateUsing(fn($state) => $state === 'open' ? 'Abierta' : 'Cerrada'),
            ])
            ->defaultSort('opened_at', 'desc')
            ->striped()
            ->actions([
                // 🟢 METEMOS TODO DENTRO DE UN ACTION GROUP (Los tres puntitos)
                ActionGroup::make([

                    // 1. Botón Original de Auditar (Ver detalles)
                    Action::make('ver_detalle_completo')
                        ->label('Auditar Detalle')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn(SessionCashRegister $record): string => ReporteCajaDetalles::getUrl(['session_id' => $record->id])),

                    // 2. Botón para PDF A4
                    Action::make('imprimir_a4')
                        ->label('Arqueo (Formato A4)')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->action(function (SessionCashRegister $record) {
                            $data = $this->prepararDatosPdfCaja($record);
                            $pdf = Pdf::loadView('pdf.arqueo-caja', $data)->setPaper('a4', 'portrait');
                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                "Arqueo_" . $record->session_code . ".pdf"
                            );
                        }),

                    // 3. Botón para PDF TICKET (80mm)
                    Action::make('imprimir_ticket')
                        ->label('Arqueo (Formato Ticket)')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('success')
                        ->action(function (SessionCashRegister $record) {
                            $data = $this->prepararDatosPdfCaja($record);

                            // 🟢 Forzamos el papel de 80mm (226.77 pt) y le indicamos CERO MÁRGENES
                            $pdf = Pdf::loadView('pdf.arqueo-caja-ticket', $data)
                                ->setPaper(array(0, 0, 226.77, 800), 'portrait')
                                ->setOption('margin-top', 5)
                                ->setOption('margin-bottom', 5)
                                ->setOption('margin-left', 5)
                                ->setOption('margin-right', 5);

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                "Ticket_Arqueo_" . $record->session_code . ".pdf"
                            );
                        }),

                ])
                    ->tooltip('Opciones de Caja') // Tooltip al pasar el mouse
                    ->icon('heroicon-m-ellipsis-vertical') // El ícono de tres puntitos
            ]);
    }

    // 🟢 PREPARA LOS DATOS EXACTOS PARA EL FORMATO TICKET Y A4
    public function prepararDatosPdfCaja(SessionCashRegister $caja): array
    {
        $caja->load(['user', 'cashRegister', 'cierreCajaDetalles.paymentMethod']);

        $movimientos = CashRegisterMovement::with('paymentMethod')
            ->where('session_cash_register_id', $caja->id)
            ->get();

        // 1. Totales de Efectivo Físico (Para la sección 1)
        $efectivo_apertura = 0;
        $efectivo_ventas = 0;
        $efectivo_entradas = 0;
        $efectivo_salidas = 0;
        $entradas_efectivo_detalle = [];
        $salidas_efectivo_detalle = [];

        // 2. Mapeo de Resumen por Método (Sistema vs Cajero)
        $resumen_metodos = [];
        $total_sistema_global = 0;
        $total_cajero_global = 0;

        // Inicializar con lo que el cajero declaró (para que aparezcan todos los métodos cerrados)
        foreach ($caja->cierreCajaDetalles as $detalle) {
            $nombre = strtoupper($detalle->paymentMethod?->name ?? 'DESCONOCIDO');
            $resumen_metodos[$nombre] = [
                'qty' => 0,
                'sistema' => (float) $detalle->monto_sistema,
                'cajero' => (float) $detalle->monto_cajero,
                'diferencia' => (float) $detalle->diferencia,
            ];
            $total_cajero_global += (float) $detalle->monto_cajero;
        }

        // Procesar movimientos para conteo y detalle de efectivo
        foreach ($movimientos as $m) {
            $monto = (float) $m->monto;
            $motivo = strtoupper($m->motivo ?? 'OTROS');
            $tipo = strtolower($m->tipo);
            $nombre_metodo = strtoupper($m->paymentMethod?->name ?? 'SIN METODO');
            $es_efectivo = str_contains($nombre_metodo, 'EFECTIVO');

            // Conteo de operaciones (solo ventas/pedidos)
            if (isset($resumen_metodos[$nombre_metodo]) && (str_contains($motivo, 'VENTA') || str_contains($motivo, 'PEDIDO'))) {
                $resumen_metodos[$nombre_metodo]['qty']++;
            }

            if (str_contains($motivo, 'APERTURA')) {
                $total_sistema_global += $monto;
                if ($es_efectivo) $efectivo_apertura += $monto;
            } elseif ($tipo === 'ingreso') {
                $total_sistema_global += $monto;
                if (str_contains($motivo, 'VENTA') || str_contains($motivo, 'PEDIDO')) {
                    if ($es_efectivo) $efectivo_ventas += $monto;
                } else {
                    if ($es_efectivo) {
                        $efectivo_entradas += $monto;
                        $entradas_efectivo_detalle[$motivo] = ($entradas_efectivo_detalle[$motivo] ?? 0) + $monto;
                    }
                }
            } elseif ($tipo === 'egreso') {
                $total_sistema_global -= $monto;
                if ($es_efectivo) {
                    $efectivo_salidas += $monto;
                    if (!str_contains($motivo, 'VENTA')) {
                        $salidas_efectivo_detalle[$motivo] = ($salidas_efectivo_detalle[$motivo] ?? 0) + $monto;
                    }
                }
            }
        }

        $efectivo_esperado = ($efectivo_apertura + $efectivo_ventas + $efectivo_entradas) - $efectivo_salidas;
        $anulaciones = $movimientos->whereIn('motivo', ['ANULACION', 'CANCELACION', 'DEVOLUCION']);

        return [
            'caja' => $caja,
            'restaurant' => Filament::getTenant()->name,
            'fecha_impresion' => now()->format('d-m-Y h:i A'),
            'usuario_impresion' => Auth::user()->name ?? 'SISTEMA',
            'efectivo_apertura' => $efectivo_apertura,
            'efectivo_ventas' => $efectivo_ventas,
            'efectivo_entradas' => $efectivo_entradas,
            'efectivo_salidas' => $efectivo_salidas,
            'efectivo_esperado' => $efectivo_esperado,
            'entradas_efectivo_detalle' => $entradas_efectivo_detalle,
            'salidas_efectivo_detalle' => $salidas_efectivo_detalle,
            'resumen_metodos' => $resumen_metodos,
            'total_sistema' => $total_sistema_global,
            'total_cajero' => $total_cajero_global,
            'anulaciones_qty' => $movimientos->where('tipo', 'egreso')->filter(fn($m) => str_contains(strtoupper($m->motivo), 'ANULA'))->count(),
            'anulaciones_total' => $movimientos->where('tipo', 'egreso')->filter(fn($m) => str_contains(strtoupper($m->motivo), 'ANULA'))->sum('monto'),
        ];
    }
}
