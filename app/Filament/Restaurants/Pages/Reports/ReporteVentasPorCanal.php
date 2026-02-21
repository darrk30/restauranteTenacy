<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Exports\VentasExport; // Usaremos la lógica de exportación dinámica del reporte 1
use App\Filament\Restaurants\Resources\SaleResource;
use App\Filament\Restaurants\Widgets\VentasCanalStats;
use App\Models\DocumentSerie;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList; // Para el exportar
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class ReporteVentasPorCanal extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Reporte Ventas';
    protected static ?string $title = 'Reporte de Ventas Unificado';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 50;
    protected static string $view = 'filament.reports.ventas.reporte-ventas-por-canal';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => now()->startOfMonth(),
            'fecha_hasta' => now()->endOfMonth(),
            'status' => 'completado', // Valor por defecto útil
        ]);
    }

    // Este método actualiza los widgets cuando cambias el formulario superior
    public function updated($name, $value)
    {
        if (str_starts_with($name, 'data')) {
            $this->dispatch('update-stats', filters: $this->data);
        }
    }

    // Acción de Cabecera: EXPORTAR EXCEL (Lógica del Reporte 1 adaptada)
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarExcel')
                ->label('Exportar Reporte')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    CheckboxList::make('columnas_reporte')
                        ->label('Selecciona las columnas para el Excel')
                        ->options([
                            'fecha_emision'           => 'Fecha de Emisión',
                            'tipo_comprobante'        => 'Tipo de Comprobante',
                            'comprobante'             => 'Comprobante (Serie-Corr)',
                            'orden_codigo'            => 'Código de Pedido',
                            'nombre_cliente'          => 'Cliente',
                            'documento_identidad'     => 'Doc. Identidad',
                            'canal'                   => 'Canal de Venta', // Agregado del R2
                            'mozo'                    => 'Mozo / Atendió',
                            'monto_especifico_filtro' => 'Monto por Método (Filtro)',
                            'op_gravada'              => 'Op. Gravada',
                            'monto_igv'               => 'IGV',
                            'monto_descuento'         => 'Descuento Aplicado',
                            'total'                   => 'Monto Total',
                            'status'                  => 'Estado',
                            'notas'                   => 'Notas/Observaciones',
                        ])
                        ->default(['fecha_emision', 'comprobante', 'nombre_cliente', 'canal', 'total', 'status'])
                        ->columns(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // 1. Reconstruimos la query basada en el formulario ($this->data)
                    // NO usamos $livewire->getFilteredTableQuery() porque aquí el filtro es manual
                    $query = Sale::query()->latest('fecha_emision');

                    // Aplicamos los mismos filtros que en la tabla
                    $filtrosForm = $this->data;
                    if (!empty($filtrosForm['fecha_desde'])) $query->whereDate('fecha_emision', '>=', $filtrosForm['fecha_desde']);
                    if (!empty($filtrosForm['fecha_hasta'])) $query->whereDate('fecha_emision', '<=', $filtrosForm['fecha_hasta']);
                    if (!empty($filtrosForm['canal'])) $query->where('canal', $filtrosForm['canal']);
                    if (!empty($filtrosForm['serie'])) $query->where('serie', $filtrosForm['serie']);
                    if (!empty($filtrosForm['numero'])) $query->where('correlativo', 'like', "%{$filtrosForm['numero']}%");
                    if (!empty($filtrosForm['status'])) $query->where('status', $filtrosForm['status']);
                    if (!empty($filtrosForm['payment_method_id'])) {
                        $query->whereHas('movements', fn($q) => $q->where('payment_method_id', $filtrosForm['payment_method_id']));
                    }

                    // 2. Preparamos etiquetas de filtros aplicados para el Excel header
                    $filtrosAplicados = [];
                    if (!empty($filtrosForm['fecha_desde'])) $filtrosAplicados['Desde'] = $filtrosForm['fecha_desde'];
                    if (!empty($filtrosForm['fecha_hasta'])) $filtrosAplicados['Hasta'] = $filtrosForm['fecha_hasta'];
                    if (!empty($filtrosForm['payment_method_id'])) {
                        $filtrosAplicados['Método'] = PaymentMethod::find($filtrosForm['payment_method_id'])?->name;
                    }
                    if (!empty($filtrosForm['status'])) $filtrosAplicados['Estado'] = ucfirst($filtrosForm['status']);

                    // 3. Orden de columnas
                    $ordenMaestro = [
                        'fecha_emision',
                        'tipo_comprobante',
                        'comprobante',
                        'orden_codigo',
                        'nombre_cliente',
                        'documento_identidad',
                        'canal',
                        'mozo',
                        'monto_especifico_filtro',
                        'op_gravada',
                        'monto_igv',
                        'monto_descuento',
                        'total',
                        'status',
                        'notas'
                    ];
                    $columnasOrdenadas = array_values(array_intersect($ordenMaestro, $data['columnas_reporte']));

                    return Excel::download(
                        new VentasExport($query, $columnasOrdenadas, $filtrosAplicados),
                        'reporte-ventas-unificado-' . now()->format('d-m-Y_His') . '.xlsx'
                    );
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filtros de Búsqueda')
                    ->schema([
                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'md' => 3,
                                'xl' => 4, // Aumentamos columnas para que quepa todo
                            ])
                            ->schema([
                                DatePicker::make('fecha_desde')
                                    ->label('Desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->default(now()->startOfMonth())
                                    ->live(),

                                DatePicker::make('fecha_hasta')
                                    ->label('Hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->default(now()->endOfMonth())
                                    ->live(),

                                Select::make('canal')
                                    ->label('Canal de Venta')
                                    ->options([
                                        'salon' => 'Salón',
                                        'delivery' => 'Delivery',
                                        'llevar' => 'Para Llevar',
                                    ])
                                    ->placeholder('Todos los canales')
                                    ->live(),

                                // --- NUEVO: Filtro Método de Pago del R1 ---
                                Select::make('payment_method_id')
                                    ->label('Método de Pago')
                                    ->options(PaymentMethod::pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('Todos los métodos')
                                    ->live(),

                                Select::make('serie')
                                    ->label('Serie')
                                    ->options(fn() => DocumentSerie::where('is_active', true)->pluck('serie', 'serie'))
                                    ->searchable()
                                    ->live(),

                                TextInput::make('numero')
                                    ->label('Nro Comprobante')
                                    ->placeholder('Ej: 0000123')
                                    ->live(debounce: 500),

                                // --- NUEVO: Filtro Estado del R1 ---
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'completado' => 'Completado',
                                        'anulado' => 'Anulado'
                                    ])
                                    ->default('completado')
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
                $query = Sale::query()
                    ->with(['movements.paymentMethod', 'user', 'order']) // Eager Loading del R1
                    ->latest('fecha_emision');

                // APLICACIÓN DE FILTROS DEL FORMULARIO SUPERIOR
                if ($data = $this->data) {
                    if (!empty($data['fecha_desde'])) $query->whereDate('fecha_emision', '>=', $data['fecha_desde']);
                    if (!empty($data['fecha_hasta'])) $query->whereDate('fecha_emision', '<=', $data['fecha_hasta']);
                    if (!empty($data['canal'])) $query->where('canal', $data['canal']);
                    if (!empty($data['serie'])) $query->where('serie', $data['serie']);
                    if (!empty($data['numero'])) $query->where('correlativo', 'like', "%{$data['numero']}%");

                    // Nuevos filtros
                    if (!empty($data['status'])) $query->where('status', $data['status']);
                    if (!empty($data['payment_method_id'])) {
                        $query->whereHas('movements', fn($q) => $q->where('payment_method_id', $data['payment_method_id']));
                    }
                }
                return $query;
            })
            ->columns([
                // Columna Fecha mejorada (R1)
                TextColumn::make('fecha_emision')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y')
                    ->description(fn($record) => '🕒 ' . $record->fecha_emision->format('H:i A'))
                    ->sortable(),

                TextColumn::make('tipo_comprobante')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn(Sale $r) => $r->serie . '-' . $r->correlativo)
                    ->searchable(['serie', 'correlativo'])
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    // 🟢 REDIRECCIÓN SPA:
                    ->url(fn(Sale $record): string => SaleResource::getUrl('view', ['record' => $record]))
                    // El método correcto para columnas es openUrlInNewTab(false)
                    ->openUrlInNewTab(false),

                TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->description(fn($record) => "{$record->tipo_documento}: {$record->numero_documento}")
                    ->searchable()
                    ->limit(25),

                // Columna Canal (Original R2)
                TextColumn::make('canal')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'salon' => 'warning',
                        'llevar' => 'success',
                        'delivery' => 'info',
                        default => 'gray',
                    }),

                // Columnas Toggleables (R1)
                TextColumn::make('user.name')->label('Mozo')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order.code')->label('Pedido')->toggleable(isToggledHiddenByDefault: true),

                // COLUMNA MAGICA: Monto por Método de Pago (R1 adaptado a form filter)
                TextColumn::make('monto_especifico_filtro')
                    ->label(function () {
                        $metodoId = $this->data['payment_method_id'] ?? null;
                        $nombre = $metodoId ? PaymentMethod::find($metodoId)?->name : 'Método';
                        return "Recaudado ({$nombre})";
                    })
                    ->money('PEN')
                    ->color('success')
                    ->state(function ($record) {
                        $metodoId = $this->data['payment_method_id'] ?? null;
                        if (!$metodoId) return 0;
                        return $record->movements
                            ->where('payment_method_id', $metodoId)
                            ->where('status', 'aprobado')
                            ->sum('monto');
                    })
                    ->visible(fn() => !empty($this->data['payment_method_id'])),

                TextColumn::make('total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn($record) => $record->status === 'anulado' ? 'gray' : 'success'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'completado' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                // Acción Ver Detalle (R1)
                TableAction::make('detalles')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->modalHeading('Detalle de Venta')
                    ->modalSubmitAction(false)
                    ->infolist(fn(Infolist $infolist) => $infolist->schema([
                        ViewEntry::make('detalle_venta')
                            ->view('filament.reports.ventas.venta-detalle')
                            ->columnSpanFull()
                    ])),
            ])
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VentasCanalStats::make(['filters' => $this->data]),
        ];
    }
}
