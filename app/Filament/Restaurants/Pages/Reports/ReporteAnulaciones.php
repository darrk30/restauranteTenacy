<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\Table as RestaurantTable; // 🟢 Importamos el modelo Table
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteAnulaciones extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-minus';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 75;
    protected static ?string $navigationLabel = 'Anulaciones';
    protected static ?string $title = 'Reporte de Anulaciones';
    protected static string $view = 'filament.reports.ordenes.reporte-anulaciones';

    public ?array $data = [];
    public string $activeTab = 'ordenes'; // Controla si vemos Órdenes o Productos

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $pdf = Pdf::loadView('pdf.reporte-anulaciones', $this->prepararDatosParaPdf());
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
            'table_id' => null, // 🟢 Inicializamos el nuevo filtro
        ]);
    }

    // 🟢 Sincronización idéntica al reporte de Ganancias (Captura cambios en el formulario)
    public function updated($name, $value)
    {
        if (str_starts_with($name, 'data')) {
            // Enviamos los filtros y la pestaña actual al widget
            $this->dispatch('update-anulaciones-stats', filters: $this->data, tab: $this->activeTab);
        }
    }

    // 🟢 Función para cambiar de pestaña desde el Blade
    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetTable(); // Recarga la tabla
        // Actualizamos el widget con la nueva pestaña
        $this->dispatch('update-anulaciones-stats', filters: $this->data, tab: $this->activeTab);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AnulacionesStats::make([
                'filters' => $this->data,
                'currentTab' => $this->activeTab,
            ]),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Búsqueda')
                    ->schema([
                        // 🟢 Cambiamos a Grid de 5 para que quepa el nuevo filtro cómodamente, o lo dejamos en 4 y que baje de línea
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

                            Select::make('canal')
                                ->label('Canal')
                                ->native(false)
                                ->placeholder('Todos')
                                ->options(['salon' => 'Salón', 'delivery' => 'Delivery', 'llevar' => 'Para Llevar'])
                                ->live(),

                            Select::make('user_id')
                                ->label('Responsable')
                                ->placeholder('Todos')
                                ->searchable()
                                ->options(User::pluck('name', 'id'))
                                ->live(),
                                
                            // 🟢 NUEVO FILTRO: MESA
                            Select::make('table_id')
                                ->label('Mesa')
                                ->placeholder('Todas las mesas')
                                ->searchable()
                                ->options(
                                    RestaurantTable::where('restaurant_id', Filament::getTenant()->id)
                                        ->pluck('name', 'id')
                                )
                                ->live(),
                        ]),
                    ])->collapsible(),
            ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        if ($this->activeTab === 'ordenes') {
            return $table
                ->query(function () {
                    $query = Order::query()
                        ->where('restaurant_id', Filament::getTenant()->id)
                        ->where('status', 'cancelado')
                        ->with(['user', 'userActualiza', 'table']); // 🟢 Cargamos la relación table

                    $filtros = $this->data;
                    if (!empty($filtros['fecha_desde'])) $query->where('created_at', '>=', $filtros['fecha_desde']);
                    if (!empty($filtros['fecha_hasta'])) $query->where('created_at', '<=', $filtros['fecha_hasta']);
                    if (!empty($filtros['canal'])) $query->where('canal', $filtros['canal']);
                    if (!empty($filtros['user_id'])) $query->where('user_id', $filtros['user_id']);
                    // 🟢 Aplicamos el filtro de mesa a la orden
                    if (!empty($filtros['table_id'])) $query->where('table_id', $filtros['table_id']);

                    return $query;
                })
                ->columns([
                    TextColumn::make('code')->label('Nro Pedido')->searchable()->sortable(),
                    TextColumn::make('created_at')->label('Fecha y Hora')->dateTime('d/m/Y h:i A')->sortable(),
                    TextColumn::make('canal')->label('Canal')->badge()->color('warning'),
                    // 🟢 NUEVA COLUMNA: MESA
                    TextColumn::make('table.name')->label('Mesa')->default('---'),
                    TextColumn::make('user.name')->label('Mozo Atendió'),
                    TextColumn::make('userActualiza.name')->label('Mozo Anuló')->color('danger'),
                    TextColumn::make('total')->label('Monto')->money('PEN')->color('danger')->weight('bold'),
                ])
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->query(function () {
                    $filtros = $this->data;
                    return OrderDetail::query()
                        // 🟢 Nos aseguramos de cargar la tabla anidada (order.table)
                        ->with(['order.table', 'user', 'userActualiza'])
                        ->whereHas('order', function ($q) use ($filtros) {
                            $q->where('restaurant_id', Filament::getTenant()->id);
                            // Las fechas las podemos seguir filtrando por la orden principal si así lo prefieres,
                            // o filtrar por la fecha del detalle. Usualmente se filtra por la fecha de anulación del detalle.
                            // Aquí mantenemos la lógica actual: filtramos basándonos en la orden.
                            if (!empty($filtros['fecha_desde'])) $q->where('created_at', '>=', $filtros['fecha_desde']);
                            if (!empty($filtros['fecha_hasta'])) $q->where('created_at', '<=', $filtros['fecha_hasta']);
                            if (!empty($filtros['canal'])) $q->where('canal', $filtros['canal']);
                            // 🟢 Aplicamos el filtro de mesa a la orden que contiene el producto
                            if (!empty($filtros['table_id'])) $q->where('table_id', $filtros['table_id']);
                        })
                        ->where('status', 'cancelado')
                        // Filtramos el usuario que anuló el producto
                        ->when(!empty($filtros['user_id']), fn($q) => $q->where('updated_by', $filtros['user_id']));
                })
                ->columns([
                    TextColumn::make('order.code')->label('Orden')->searchable()->sortable(),
                    // 🟢 NUEVA COLUMNA: FECHA Y HORA DEL DETALLE ANULADO
                    TextColumn::make('updated_at')->label('Fecha Anulación')->dateTime('d/m/Y h:i A')->sortable(),
                    // 🟢 NUEVA COLUMNA: MESA
                    TextColumn::make('order.table.name')->label('Mesa')->default('---'),
                    TextColumn::make('product_name')->label('Producto / Detalle')->searchable(),
                    TextColumn::make('item_type')->label('Tipo')->badge()
                        ->color(fn($state) => $state === 'Promocion' ? 'warning' : 'info'),
                    TextColumn::make('user.name')->label('Atendió')->size('sm'),
                    TextColumn::make('userActualiza.name')->label('Anuló')->color('danger')->size('sm'),
                    IconColumn::make('cortesia')->label('Cort.')->boolean(),
                    TextColumn::make('cantidad')->label('Cant.')->alignCenter(),
                    TextColumn::make('price')->label('Precio')->money('PEN'),
                    TextColumn::make('subTotal')->label('SubTotal')->money('PEN')->color('danger')->weight('bold'),
                ])
                ->defaultSort('updated_at', 'desc'); // Ordenamos por fecha de actualización (cuando se anuló)
        }
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
                ->with(['user', 'userActualiza', 'table']); // Cargamos table

            if (!empty($filtros['fecha_desde'])) $query->where('created_at', '>=', $filtros['fecha_desde']);
            if (!empty($filtros['fecha_hasta'])) $query->where('created_at', '<=', $filtros['fecha_hasta']);
            if (!empty($filtros['canal'])) $query->where('canal', $filtros['canal']);
            if (!empty($filtros['user_id'])) $query->where('user_id', $filtros['user_id']);
            if (!empty($filtros['table_id'])) $query->where('table_id', $filtros['table_id']); // Filtro mesa

            $anulaciones = $query->latest('created_at')->get();
            $cantidadTotal = $anulaciones->count();
            $montoTotal = $anulaciones->sum('total');
        } else {
            $query = OrderDetail::query()
                ->with(['order.table', 'user', 'userActualiza']) // Cargamos order.table
                ->whereHas('order', function ($q) use ($tenant, $filtros) {
                    $q->where('restaurant_id', $tenant->id);
                    if (!empty($filtros['fecha_desde'])) $q->where('created_at', '>=', $filtros['fecha_desde']);
                    if (!empty($filtros['fecha_hasta'])) $q->where('created_at', '<=', $filtros['fecha_hasta']);
                    if (!empty($filtros['canal'])) $q->where('canal', $filtros['canal']);
                    if (!empty($filtros['table_id'])) $q->where('table_id', $filtros['table_id']); // Filtro mesa
                })
                ->where('status', 'cancelado')
                ->when(!empty($filtros['user_id']), fn($q) => $q->where('updated_by', $filtros['user_id']));

            $anulaciones = $query->latest('updated_at')->get(); // Usamos updated_at
            $cantidadTotal = $anulaciones->sum('cantidad');
            $montoTotal = $anulaciones->sum('subTotal');
        }

        // Obtener nombre de la mesa para el PDF si hay filtro
        $nombreMesa = 'Todas';
        if (!empty($filtros['table_id'])) {
            $mesa = RestaurantTable::find($filtros['table_id']);
            $nombreMesa = $mesa ? $mesa->name : 'Todas';
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
                'Mesa' => $nombreMesa, // 🟢 Mostramos el filtro de mesa en el PDF
            ],
            'totales' => [
                'cantidad' => $cantidadTotal,
                'monto' => $montoTotal,
            ],
        ];
    }
}