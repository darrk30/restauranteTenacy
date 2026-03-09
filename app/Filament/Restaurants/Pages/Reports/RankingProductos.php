<?php

namespace App\Filament\Restaurants\Pages\Reports;

use Filament\Pages\Page;
use App\Models\SaleDetail;
use App\Models\Category;
use App\Models\Production;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class RankingProductos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Ranking de Productos';
    protected static ?string $title = 'Ranking de Productos';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 70;
    protected static string $view = 'filament.reports.ventas.ranking-productos';

    // Propiedades públicas NECESARIAS para la sincronización de Livewire
    public $filter_type = 'mensual';
    public $fecha_desde;
    public $fecha_hasta;
    public $category_id;

    public function mount()
    {
        $this->fecha_desde = now()->subDays(30)->toDateString();
        $this->fecha_hasta = now()->toDateString();

        $this->form->fill([
            'filter_type' => $this->filter_type,
            'fecha_desde' => $this->fecha_desde,
            'fecha_hasta' => $this->fecha_hasta,
            'category_id' => null,
        ]);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        
        // 1. Pase VIP
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // 2. Determinamos el sufijo según el panel
        $sufijo = Filament::getTenant() ? '_rest' : '_admin';
        $permisoBuscado = 'ver_reporte_ranking_productos' . $sufijo;

        // 3. Escudo Anti-Crash
        try {
            return $user->hasPermissionTo($permisoBuscado);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false; // Si olvidaste crear el permiso, oculta la página sin crashear
        }
    }

    protected function getFormSchema(): array
    {
        return [
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
                                $set('fecha_desde', $hoy->toDateString());
                                $set('fecha_hasta', $hoy->toDateString());
                            } elseif ($state === 'semanal') {
                                $set('fecha_desde', $hoy->copy()->subDays(7)->toDateString());
                                $set('fecha_hasta', $hoy->toDateString());
                            } elseif ($state === 'mensual') {
                                $set('fecha_desde', $hoy->copy()->subDays(30)->toDateString());
                                $set('fecha_hasta', $hoy->toDateString());
                            }
                        }),

                    Select::make('category_id')
                        ->label('Categoría')
                        ->placeholder('Todas las categorías (Ver por Áreas)')
                        ->options(Category::where('status', true)->pluck('name', 'id'))
                        ->live(),

                    DatePicker::make('fecha_desde')
                        ->label('Desde')
                        ->required()
                        ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                        ->live(),

                    DatePicker::make('fecha_hasta')
                        ->label('Hasta')
                        ->required()
                        ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                        ->live(),
                ]),
        ];
    }

    public function getRankings()
    {
        $filtros = $this->form->getState();
        $categoryId = $filtros['category_id'] ?? null;

        // Caso 1: Se seleccionó una categoría específica
        if ($categoryId) {
            $cat = Category::find($categoryId);
            return [
                [
                    'titulo' => 'Categoría: ' . $cat->name,
                    'data' => $this->executeQuery(null, $categoryId)
                ]
            ];
        }

        // Caso 2: Mostrar todos los rankings divididos por Área de Producción
        return Production::where('restaurant_id', Filament::getTenant()->id)
            ->get()
            ->map(function ($area) {
                return [
                    'id' => $area->id,
                    'titulo' => 'Área: ' . $area->name,
                    'data' => $this->executeQuery($area->id, null)
                ];
            })
            ->filter(fn($area) => $area['data']->count() > 0);
    }

    private function executeQuery($productionId = null, $catId = null)
    {
        $data = $this->form->getState();
        $desde = $data['fecha_desde'] ?? $this->fecha_desde;
        $hasta = $data['fecha_hasta'] ?? $this->fecha_hasta;

        return SaleDetail::query()
            ->select(
                'sale_details.product_name',
                'sale_details.variant_id',
                'sale_details.promotion_id',
                DB::raw('SUM(sale_details.cantidad) as total_cantidad'),
                DB::raw('SUM(sale_details.subtotal) as total_dinero')
            )
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            // 1. Join a productos
            ->leftJoin('products', 'sale_details.product_id', '=', 'products.id')
            // 2. Join a promociones para obtener su production_id
            ->leftJoin('promotions', 'sale_details.promotion_id', '=', 'promotions.id')

            ->where('sales.restaurant_id', Filament::getTenant()->id)
            ->where('sales.status', 'completado')

            // 🟢 FILTRO DE ÁREA CORREGIDO
            ->when($productionId, function ($q) use ($productionId) {
                $q->where(function ($sub) use ($productionId) {
                    // Si es producto, usa products.production_id
                    // Si es promo, usa promotions.production_id
                    $sub->where('products.production_id', $productionId)
                        ->orWhere('promotions.production_id', $productionId);
                });
            })

            // (Mantén el resto de tus filtros de categoría y fechas igual...)
            ->whereDate('sales.fecha_emision', '>=', $desde)
            ->whereDate('sales.fecha_emision', '<=', $hasta)
            ->groupBy('sale_details.product_name', 'sale_details.variant_id', 'sale_details.promotion_id')
            ->orderByDesc('total_cantidad')
            ->limit(10)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarPDF')
                ->label('Exportar Reporte')
                ->color('danger')
                ->icon('heroicon-o-document-arrow-down')
                // Agregamos el parámetro tipado $form
                ->form(function (Form $form): array {
                    // Usamos la propiedad pública directamente para evitar conflictos de estado
                    $catId = $this->category_id;

                    if ($catId) return [];

                    return [
                        Select::make('area_export')
                            ->label('¿Qué ranking desea exportar?')
                            ->options([
                                'todas' => 'Todas las áreas (PDF consolidado)',
                            ] + Production::where('restaurant_id', Filament::getTenant()->id)->pluck('name', 'id')->toArray())
                            ->default('todas')
                            ->required()
                    ];
                })
                ->action(function (array $data) {
                    $filtros = $this->form->getState();
                    $catId = $filtros['category_id'] ?? null;

                    if ($catId) {
                        $rankings = $this->getRankings();
                    } else {
                        if ($data['area_export'] === 'todas') {
                            $rankings = $this->getRankings();
                        } else {
                            $area = Production::find($data['area_export']);
                            $rankings = [[
                                'titulo' => 'Área: ' . $area->name,
                                'data' => $this->executeQuery($area->id, null)
                            ]];
                        }
                    }

                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ranking-productos', [
                        'rankings' => $rankings,
                        'restaurant' => Filament::getTenant(),
                        'desde' => $filtros['fecha_desde'] ?? $this->fecha_desde,
                        'hasta' => $filtros['fecha_hasta'] ?? $this->fecha_hasta,
                        'fecha' => now()->format('d/m/Y H:i A')
                    ]);

                    return response()->streamDownload(fn() => print($pdf->output()), "ranking-ventas-" . now()->format('Y-m-d') . ".pdf");
                }),
        ];
    }

    public function aplicarFiltros()
    {
        Notification::make()->title('Filtros aplicados')->success()->send();
    }
}
