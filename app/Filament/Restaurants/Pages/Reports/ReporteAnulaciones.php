<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Filament\Restaurants\Widgets\AnulacionesStats;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Forms\Components\DateTimePicker;
// 🟢 IMPORTA LAS ACCIONES DE PÁGINA Y PDF
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteAnulaciones extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 75;
    protected static ?string $navigationLabel = 'Anulaciones';
    protected static ?string $title = 'Reporte de Anulaciones';
    protected static string $view = 'filament.reports.ordenes.reporte-anulaciones';

    public ?array $data = [];
    public string $activeTab = 'ordenes';

    // 🟢 ESTA ES LA FUNCIÓN QUE FALTA PARA MOSTRAR EL BOTÓN
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    // Cargamos la vista del PDF con los datos preparados
                    $pdf = Pdf::loadView('pdf.reporte-anulaciones', $this->prepararDatosParaPdf());

                    // Descargamos el archivo
                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        "Reporte_Anulaciones_" . now()->format('d-m-Y') . ".pdf"
                    );
                }),
        ];
    }

    public function mount()
    {
        $this->form->fill([
            'fecha_desde' => now()->startOfDay()->toDateTimeString(),
            'fecha_hasta' => now()->endOfDay()->toDateTimeString(),
            'canal' => null,
            'user_id' => null,
        ]);
    }

    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetTable();
        $this->dispatch('update-anulaciones-stats', filters: ['activeTab' => $tab]);
    }

    public function updatedData()
    {
        $this->dispatch('update-anulaciones-stats', filters: $this->data);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnulacionesStats::make(['filters' => $this->data]),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Búsqueda')
                    ->schema([
                        Grid::make(4)->schema([
                            
                            DateTimePicker::make('fecha_desde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y h:i A')
                                    ->format('Y-m-d H:i:s')
                                    ->seconds(false)
                                    ->default(now()->startOfMonth())
                                    ->live(),

                                DateTimePicker::make('fecha_hasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y h:i A')
                                    ->format('Y-m-d H:i:s')
                                    ->seconds(false)
                                    ->default(now()->endOfMonth())
                                    ->live(),
                            Select::make('canal')->label('Canal')->native(false)->placeholder('Todos')->live()
                                ->options(['salon' => 'Salón', 'delivery' => 'Delivery', 'llevar' => 'Para Llevar'])
                                ->afterStateUpdated(fn() => $this->updatedData()),
                            Select::make('user_id')->label('Responsable')->placeholder('Todos')->searchable()->live()
                                ->options(User::pluck('name', 'id'))
                                ->afterStateUpdated(fn() => $this->updatedData()),
                        ]),
                    ])->collapsible(),
            ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        if ($this->activeTab === 'ordenes') {
            return $table
                ->query(
                    Order::query()
                        ->where('restaurant_id', Filament::getTenant()->id)
                        ->where('status', 'cancelado')
                        ->with(['user', 'userActualiza'])
                        ->when($this->data['fecha_desde'], fn($q, $f) => $q->where('created_at', '>=', $f))
                        ->when($this->data['fecha_hasta'], fn($q, $f) => $q->where('created_at', '<=', $f))
                        ->when($this->data['canal'], fn($q, $f) => $q->where('canal', $f))
                        ->when($this->data['user_id'], fn($q, $f) => $q->where('user_id', $f))
                )
                ->columns([
                    TextColumn::make('code')->label('Nro Pedido')->searchable()->sortable(),
                    TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i A')->sortable(),
                    TextColumn::make('canal')->label('Canal')->badge()->color('warning'),
                    TextColumn::make('user.name')->label('Mozo Atendió'),
                    TextColumn::make('userActualiza.name')->label('Mozo Anuló')->color('danger'),
                    TextColumn::make('total')->label('Monto')->money('PEN')->color('danger')->weight('bold'),
                ]);
        } else {
            return $table
                ->query(
                    OrderDetail::query()
                        ->with(['order', 'user', 'userActualiza'])
                        ->whereHas('order', function ($q) {
                            $q->where('restaurant_id', Filament::getTenant()->id)
                                ->when($this->data['fecha_desde'], fn($sub, $f) => $sub->where('created_at', '>=', $f))
                                ->when($this->data['fecha_hasta'], fn($sub, $f) => $sub->where('created_at', '<=', $f))
                                ->when($this->data['canal'], fn($sub, $f) => $sub->where('canal', $f))
                                ->when($this->data['user_id'], fn($sub, $f) => $sub->where('user_id', $f));
                        })
                        ->where('status', 'cancelado')
                )
                ->columns([
                    TextColumn::make('order.code')->label('Orden')->searchable()->sortable(),
                    TextColumn::make('product_name')->label('Producto / Detalle')->searchable(),
                    TextColumn::make('item_type')->label('Tipo')->badge()
                        ->color(fn($state) => $state === 'Promocion' ? 'warning' : 'info'),
                    TextColumn::make('user.name')->label('Atendió')->size('sm'),
                    TextColumn::make('userActualiza.name')->label('Anuló')->color('danger')->size('sm'),
                    IconColumn::make('cortesia')->label('Cort.')->boolean(),
                    TextColumn::make('cantidad')->label('Cant.')->alignCenter(),
                    TextColumn::make('price')->label('Precio')->money('PEN'),
                    TextColumn::make('subTotal')->label('SubTotal')->money('PEN')->color('danger')->weight('bold'),
                ]);
        }
    }

    // Método para aplicar filtros en consultas crudas del PDF
    private function aplicarFiltrosQuery($query, $filtros)
    {
        if (!empty($filtros['fecha_desde'])) $query->where('created_at', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta'])) $query->where('created_at', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['canal'])) $query->where('canal', $filtros['canal']);
        if (!empty($filtros['user_id'])) $query->where('user_id', $filtros['user_id']);
    }

    public function prepararDatosParaPdf(): array
    {
        $filtros = $this->data;
        $tenant = Filament::getTenant();
        $tipo = $this->activeTab;

        if ($tipo === 'ordenes') {
            $query = Order::query()
                ->where('restaurant_id', $tenant->id)
                ->where('status', 'cancelado')
                ->with(['user', 'userActualiza']);
            $this->aplicarFiltrosQuery($query, $filtros);
            $anulaciones = $query->latest('created_at')->get();
            $cantidadTotal = $anulaciones->count();
            $montoTotal = $anulaciones->sum('total');
        } else {
            $query = OrderDetail::query()
                // 🟢 Cargamos las relaciones necesarias: orden (para canal) y usuarios
                ->with(['order', 'user', 'userActualiza'])
                ->whereHas('order', function ($q) use ($tenant, $filtros) {
                    $q->where('restaurant_id', $tenant->id);
                    $this->aplicarFiltrosQuery($q, $filtros);
                })
                ->where('status', 'cancelado');

            $anulaciones = $query->latest('created_at')->get();
            $cantidadTotal = $anulaciones->sum('cantidad');
            $montoTotal = $anulaciones->sum('subTotal');
        }

        return [
            'tipo_reporte' => $tipo,
            'anulaciones' => $anulaciones,
            'restaurant' => $tenant->name,
            'nombre_reporte' => 'REPORTE DE ANULACIONES',
            'fecha_exportacion' => now()->format('d/m/Y h:i A'),
            'filtros' => [
                'Desde' => $filtros['fecha_desde'] ? Carbon::parse($filtros['fecha_desde'])->format('d/m/Y H:i') : 'Inicio',
                'Hasta' => $filtros['fecha_hasta'] ? Carbon::parse($filtros['fecha_hasta'])->format('d/m/Y H:i') : 'Fin',
                'Canal' => ucfirst($filtros['canal'] ?? 'Todos'),
            ],
            'totales' => [
                'cantidad' => $cantidadTotal,
                'monto' => $montoTotal,
            ],
        ];
    }
}
