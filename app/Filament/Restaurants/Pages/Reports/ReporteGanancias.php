<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Sale;
use App\Filament\Restaurants\Widgets\GananciasStats;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReporteGanancias extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Reporte de Ganancias';
    protected static ?string $title = 'Análisis de Rentabilidad';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 55;
    protected static string $view = 'filament.reports.ventas.reporte-ganancias';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => now()->startOfMonth(),
            'fecha_hasta' => now()->endOfMonth(),
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
        $permisoBuscado = 'ver_reporte_ganancias' . $sufijo;

        // 3. Escudo Anti-Crash
        try {
            return $user->hasPermissionTo($permisoBuscado);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false; // Si olvidaste crear el permiso, oculta la página sin crashear
        }
    }

    // 🟢 Sincronización idéntica al reporte de ventas
    public function updated($name, $value)
    {
        if (str_starts_with($name, 'data')) {
            $this->dispatch('update-ganancias-stats', filters: $this->data);
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GananciasStats::make(['filters' => $this->data]),
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
                                ->displayFormat('d/m/Y h:i A') // 🟢 'h' minúscula para 12h y 'A' para AM/PM
                                ->format('Y-m-d H:i:s')        // 🟢 Mantenemos 'H' (24h) para que la base de datos no se confunda
                                ->seconds(false)
                                ->default(now()->startOfMonth())
                                ->live(),

                            DateTimePicker::make('fecha_hasta')
                                ->label('Hasta')
                                ->native(false)
                                ->displayFormat('d/m/Y h:i A') // 🟢 El usuario verá: 21/02/2026 11:30 AM
                                ->format('Y-m-d H:i:s')
                                ->seconds(false)
                                ->default(now()->endOfMonth())
                                ->live(),
                            Select::make('tipo_comprobante')
                                ->label('Comprobante')
                                // 🟢 Filtramos el Enum para mostrar solo lo que quieres
                                ->options([
                                    \App\Enums\DocumentSeriesType::FACTURA->value => \App\Enums\DocumentSeriesType::FACTURA->label(),
                                    \App\Enums\DocumentSeriesType::BOLETA->value => \App\Enums\DocumentSeriesType::BOLETA->label(),
                                    \App\Enums\DocumentSeriesType::NOTA_VENTA->value => \App\Enums\DocumentSeriesType::NOTA_VENTA->label(),
                                ])
                                ->placeholder('Todos')
                                ->live(),
                        ]),
                    ])->collapsible(),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                // 1. Empezamos la query base
                $query = Sale::query()
                    ->withoutGlobalScopes()
                    ->where('restaurant_id', Filament::getTenant()->id)
                    ->where('status', 'completado')
                    ->select('*')
                    ->selectRaw('(total - costo_total) as ganancia_neta');

                // 2. 🟢 APLICAMOS LOS FILTROS MANUALMENTE 🟢
                $filtros = $this->data;

                if (!empty($filtros['fecha_desde'])) {
                    // 🟢 Usamos where en lugar de whereDate para incluir la hora
                    $query->where('fecha_emision', '>=', $filtros['fecha_desde']);
                }

                if (!empty($filtros['fecha_hasta'])) {
                    // 🟢 Usamos where en lugar de whereDate para incluir la hora
                    $query->where('fecha_emision', '<=', $filtros['fecha_hasta']);
                }

                if (!empty($filtros['tipo_comprobante'])) {
                    $query->where('tipo_comprobante', $filtros['tipo_comprobante']);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),

                TextColumn::make('comprobante')
                    ->state(fn($record) => $record->serie . '-' . $record->correlativo),

                TextColumn::make('total')
                    ->label('Ingreso')
                    ->money('PEN')
                    ->color('success')
                    ->summarize(Sum::make()->label('Total')),

                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->money('PEN')
                    ->color('danger')
                    ->summarize(Sum::make()->label('Total')),

                TextColumn::make('ganancia_neta')
                    ->label('Ganancia')
                    ->money('PEN')
                    ->weight('bold')
                    ->color('primary')
                    ->summarize(Sum::make()->label('Total')),

                TextColumn::make('margen')
                    ->label('% Margen')
                    ->badge()
                    ->state(fn($record) => $record->total > 0 ? round(($record->ganancia_neta / $record->total) * 100, 1) . '%' : '0%')
                    ->color(fn($state) => (float)$state >= 30 ? 'success' : 'warning'),
            ])
            ->defaultSort('fecha_emision', 'desc');
    }
}
