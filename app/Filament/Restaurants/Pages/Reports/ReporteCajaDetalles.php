<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\SessionCashRegister;
use App\Models\CashRegisterMovement;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ReporteCajaDetalles extends Page implements HasInfolists, HasTable
{
    use InteractsWithInfolists, InteractsWithTable;

    protected static ?string $title = 'Detalle de Caja';
    protected static string $view = 'filament.reports.cajas.detalles-caja';
    protected static bool $shouldRegisterNavigation = false;

    public $session_id;
    public ?SessionCashRegister $caja = null;

    // 🟢 Variables para controlar Pestañas y Sub-pestañas
    public string $activeTab = 'todos';
    public string $activeCanal = 'todos'; // Nuevo controlador para Salón, Delivery, Llevar

    public float $monto_apertura = 0;
    public float $ventas_aprobadas = 0;
    public float $ventas_anuladas = 0;
    public float $otros_ingresos = 0;
    public float $egresos_gastos = 0;
    public float $canal_salon = 0;
    public float $canal_delivery = 0;
    public float $canal_llevar = 0;

    public function mount()
    {
        $this->session_id = request()->query('session_id');

        if (!$this->session_id) abort(404, 'No se proporcionó un ID de sesión.');

        $this->caja = SessionCashRegister::with([
            'user',
            'cashRegister',
            'cierreCajaDetalles.paymentMethod'
        ])
            ->where('restaurant_id', Filament::getTenant()->id)
            ->findOrFail($this->session_id);

        $this->calcularTotales();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ReporteCajas::getUrl() => 'Flujo de Cajas',
            '' => 'Auditoría de Sesión',
        ];
    }

    // 🟢 Cuando cambia la pestaña principal, reseteamos el canal y la tabla
    public function updatedActiveTab()
    {
        $this->activeCanal = 'todos';
        $this->resetTable();
    }

    // 🟢 Cuando cambia la sub-pestaña de canal, reseteamos la tabla
    public function updatedActiveCanal()
    {
        $this->resetTable();
    }

    protected function calcularTotales()
    {
        $movimientos = CashRegisterMovement::with('referencia')
            ->where('session_cash_register_id', $this->session_id)
            ->get();

        foreach ($movimientos as $m) {
            $motivo = strtolower($m->motivo ?? '');
            $tipo = strtolower($m->tipo ?? '');
            $monto = (float) $m->monto;

            if (str_contains($motivo, 'apertura')) {
                $this->monto_apertura += $monto;
            } elseif ($tipo === 'ingreso') {
                if (str_contains($motivo, 'venta') || str_contains($motivo, 'pedido')) {
                    $this->ventas_aprobadas += $monto;
                    $canal = strtolower(optional($m->referencia)->canal ?? '');
                    if ($canal === 'salon') $this->canal_salon += $monto;
                    elseif ($canal === 'delivery') $this->canal_delivery += $monto;
                    elseif (in_array($canal, ['llevar', 'para llevar'])) $this->canal_llevar += $monto;
                } else {
                    $this->otros_ingresos += $monto;
                }
            } elseif ($tipo === 'egreso') {
                if (str_contains($motivo, 'anula') || str_contains($motivo, 'cancel') || str_contains($motivo, 'devol')) {
                    $this->ventas_anuladas += $monto;
                } else {
                    $this->egresos_gastos += $monto;
                }
            }
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->caja)
            ->schema([
                Section::make('Información de la Sesión')
                    ->schema([
                        TextEntry::make('session_code')->label('Cod. Sesión')->weight('bold'),
                        TextEntry::make('user.name')->label('Cajero / Responsable'),
                        TextEntry::make('status')->label('Estado')->badge()->color(fn($state) => $state === 'cerrada' ? 'gray' : 'success'),
                        TextEntry::make('opened_at')->label('Fecha Apertura')->dateTime('d/m/Y h:i A'),
                        TextEntry::make('closed_at')->label('Fecha Cierre')
                            ->getStateUsing(fn($record) => $record->closed_at ? $record->closed_at->format('d/m/Y h:i A') : 'En Curso')
                            ->badge(fn($record) => !$record->closed_at)
                            ->color(fn($record) => $record->closed_at ? 'gray' : 'success'),
                    ])->columns(5),

                Section::make('Fórmula de Cuadre de Caja (Auditoría)')
                    ->description('Apertura + Ingresos Totales - Egresos Totales = Total Sistema')
                    ->schema([
                        TextEntry::make('apertura_f')->label('1. Caja Inicial')->state(fn() => $this->monto_apertura)->money('PEN')->color('gray'),
                        TextEntry::make('ingresos_f')->label('2. (+) Ingresos Totales')->state(fn() => $this->ventas_aprobadas + $this->otros_ingresos)->money('PEN')->color('success'),
                        TextEntry::make('egresos_f')->label('3. (-) Egresos Totales')->state(fn() => $this->ventas_anuladas + $this->egresos_gastos)->money('PEN')->color('danger'),
                        TextEntry::make('system_closing_amount')->label('4. (=) TOTAL SISTEMA')->money('PEN')->color('primary')->weight('bold')->badge(),
                        TextEntry::make('cajero_closing_amount')->label('TOTAL DECLARADO')->money('PEN')->color('warning')->weight('bold')->badge(),
                        TextEntry::make('difference')->label('DESCUADRE')->money('PEN')
                            ->badge()
                            ->color(fn($state) => (float)$state < 0 ? 'danger' : ((float)$state > 0 ? 'warning' : 'success'))
                            ->weight('bold'),
                    ])->columns(6),

                InfoGrid::make(4)->schema([
                    Section::make('Detalle Operativo')
                        ->schema([
                            TextEntry::make('ventas_aprobadas')->label('Ventas Aprobadas')->state(fn() => $this->ventas_aprobadas)->money('PEN')->color('success'),
                            TextEntry::make('otros_ingresos')->label('Ingresos Extras')->state(fn() => $this->otros_ingresos)->money('PEN')->color('success'),
                            TextEntry::make('ventas_anuladas')->label('Anulaciones / Devol')->state(fn() => $this->ventas_anuladas)->money('PEN')->color('danger'),
                            TextEntry::make('egresos_gastos')->label('Gastos / Retiros')->state(fn() => $this->egresos_gastos)->money('PEN')->color('danger'),
                        ])->columnSpan(1),

                    Section::make('Ventas por Canal')
                        ->schema([
                            TextEntry::make('canal_salon')->label('Salón')->state(fn() => $this->canal_salon)->money('PEN')->icon('heroicon-m-building-storefront'),
                            TextEntry::make('canal_delivery')->label('Delivery')->state(fn() => $this->canal_delivery)->money('PEN')->icon('heroicon-m-truck'),
                            TextEntry::make('canal_llevar')->label('Para Llevar')->state(fn() => $this->canal_llevar)->money('PEN')->icon('heroicon-m-shopping-bag'),
                        ])->columnSpan(1),

                    Section::make('Cierre por Métodos de Pago')
                        ->schema([
                            RepeatableEntry::make('cierreCajaDetalles')
                                ->label('')
                                ->schema([
                                    TextEntry::make('paymentMethod.name')->label('Método')->weight('bold'),
                                    TextEntry::make('monto_sistema')->label('Sistema')->money('PEN')->color('info'),
                                    TextEntry::make('monto_cajero')->label('Cajero')->money('PEN')->color('success'),
                                    TextEntry::make('diferencia')->label('Descuadre')->money('PEN')->color(fn($state) => (float)$state < 0 ? 'danger' : 'success'),
                                ])->columns(4)
                        ])->columnSpan(2),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = CashRegisterMovement::query()
                    ->where('session_cash_register_id', $this->session_id)
                    ->with(['paymentMethod', 'user', 'referencia']);

                // 🟢 Lógica de filtrado de PESTAÑA PRINCIPAL
                if ($this->activeTab === 'apertura') {
                    $query->where('motivo', 'like', '%Apertura%');
                } elseif ($this->activeTab === 'ventas') {
                    $query->where('tipo', 'ingreso')->where(function ($q) {
                        $q->where('motivo', 'like', '%Venta%')->orWhere('motivo', 'like', '%Pedido%');
                    });
                } elseif ($this->activeTab === 'ingresos') {
                    $query->where('tipo', 'ingreso')
                        ->where('motivo', 'not like', '%Venta%')
                        ->where('motivo', 'not like', '%Pedido%')
                        ->where('motivo', 'not like', '%Apertura%');
                } elseif ($this->activeTab === 'anulaciones') {
                    $query->where('tipo', 'egreso')->where(function ($q) {
                        $q->where('motivo', 'like', '%Anula%')->orWhere('motivo', 'like', '%Cancel%')->orWhere('motivo', 'like', '%Devol%');
                    });
                } elseif ($this->activeTab === 'egresos') {
                    $query->where('tipo', 'egreso')
                        ->where('motivo', 'not like', '%Anula%')
                        ->where('motivo', 'not like', '%Cancel%')
                        ->where('motivo', 'not like', '%Devol%');
                }

                // 🟢 Lógica de filtrado de SUB-PESTAÑA (Canales)
                if (in_array($this->activeTab, ['ventas', 'anulaciones']) && $this->activeCanal !== 'todos') {
                    // 🟢 SOLUCIÓN: Cambiamos el '*' por un array con los modelos que SÍ tienen la columna 'canal'
                    $query->whereHasMorph('referencia', [\App\Models\Sale::class], function (Builder $q) {
                        if ($this->activeCanal === 'llevar') {
                            $q->whereIn('canal', ['llevar', 'para llevar']);
                        } else {
                            $q->where('canal', $this->activeCanal);
                        }
                    });
                }

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Hora')->dateTime('h:i A')->sortable(),
                TextColumn::make('tipo')->label('Clasificación')->badge()->color(fn($state) => $state === 'ingreso' ? 'success' : 'danger'),
                TextColumn::make('motivo')->label('Concepto / Motivo')->description(fn($record) => $record->observacion)->searchable()->wrap(),
                TextColumn::make('paymentMethod.name')->label('Método de Pago')->badge()->color('info'),
                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->weight('bold')
                    ->color(fn($record) => $record->tipo === 'ingreso' ? 'success' : 'danger')
                    ->summarize(
                        Sum::make()
                            ->label('Total (Pestaña Actual)')
                            ->money('PEN')
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([20, 50, 100]);
    }
}
