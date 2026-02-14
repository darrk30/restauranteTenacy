<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Exports\VentasCanalExport;
use App\Filament\Restaurants\Widgets\VentasCanalStats;
use App\Models\DocumentSerie;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
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
    protected static ?string $navigationLabel = 'Reporte Detallado Ventas';
    protected static ?string $title = 'Reporte de Ventas por Canal';
    protected static ?string $navigationGroup = 'Reportes';
    protected static string $view = 'filament.reports.ventas.reporte-ventas-por-canal';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    // 👇 MAGIA AQUÍ: Este método se ejecuta CADA VEZ que cambias un filtro
    public function updated($name, $value)
    {
        // Si el cambio ocurrió dentro de la variable 'data' (nuestro formulario)
        if (str_starts_with($name, 'data')) {
            // Enviamos el evento 'update-stats' con los nuevos filtros a los widgets
            $this->dispatch('update-stats', filters: $this->data);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar_excel')
                ->label('Descargar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    // Pasamos $this->data (los filtros actuales) a la clase de exportación
                    return Excel::download(
                        new VentasCanalExport($this->data), 
                        'ventas_por_canal_' . now()->format('d-m-Y_His') . '.xlsx'
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
                                'default' => 1,  // móvil
                                'sm' => 2,       // tablet pequeña
                                'md' => 3,       // tablet grande
                                'xl' => 5,       // desktop
                            ])
                            ->schema([
                                DatePicker::make('fecha_desde')
                                    ->label('Desde')
                                    ->default(now()->startOfMonth())
                                    ->live(),

                                DatePicker::make('fecha_hasta')
                                    ->label('Hasta')
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

                                Select::make('serie')
                                    ->label('Serie')
                                    ->options(fn() => DocumentSerie::where('is_active', true)->pluck('serie', 'serie'))
                                    ->searchable()
                                    ->live(),

                                TextInput::make('numero')
                                    ->label('Nro Comprobante')
                                    ->placeholder('Ej: 0000123')
                                    ->live(debounce: 500),
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
                $query = Sale::query()->latest('fecha_emision');

                if ($data = $this->data) {
                    if (!empty($data['fecha_desde'])) $query->whereDate('fecha_emision', '>=', $data['fecha_desde']);
                    if (!empty($data['fecha_hasta'])) $query->whereDate('fecha_emision', '<=', $data['fecha_hasta']);
                    if (!empty($data['canal'])) $query->where('canal', $data['canal']);
                    if (!empty($data['serie'])) $query->where('serie', $data['serie']);
                    if (!empty($data['numero'])) $query->where('correlativo', 'like', "%{$data['numero']}%");
                }
                return $query;
            })
            ->columns([
                TextColumn::make('fecha_emision')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('tipo_comprobante')->badge()->color('gray'),
                TextColumn::make('full_documento')->label('Documento')->state(fn(Sale $r) => $r->serie . '-' . $r->correlativo),
                TextColumn::make('nombre_cliente')->label('Cliente')->limit(20),
                TextColumn::make('canal')->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'salon' => 'warning',
                        'llevar' => 'success',
                        'delivery' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('total')->money('PEN')->sortable()->weight('bold'),
                TextColumn::make('status')->badge()->color('success'),
            ])
            ->paginated([10, 25, 50]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Pasamos los filtros iniciales al cargar la página por primera vez
            VentasCanalStats::make(['filters' => $this->data]),
        ];
    }
}
