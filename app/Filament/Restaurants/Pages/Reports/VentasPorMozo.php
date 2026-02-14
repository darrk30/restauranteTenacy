<?php

namespace App\Filament\Restaurants\Pages\Reports;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Pages\Page;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class VentasPorMozo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Ventas por Mozo';
    protected static ?string $title = 'Rendimiento de Mozos';
    protected static string | \UnitEnum | null $navigationGroup = 'Reportes';
    protected string $view = 'filament.reports.operativo.ventas-por-mozo';

    public $filter_type = 'mensual';
    public $fecha_desde;
    public $fecha_hasta;

    public function mount()
    {
        $this->fecha_desde = now()->subDays(30)->toDateString();
        $this->fecha_hasta = now()->toDateString();

        $this->form->fill([
            'filter_type' => $this->filter_type,
            'fecha_desde' => $this->fecha_desde,
            'fecha_hasta' => $this->fecha_hasta,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)
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

    public function getStats()
    {
        $data = $this->form->getState();
        $desde = $data['fecha_desde'] ?? $this->fecha_desde;
        $hasta = $data['fecha_hasta'] ?? $this->fecha_hasta;

        return Sale::query()
            ->withoutGlobalScopes()
            ->select(
                'users.id as waiter_id', // <-- ESTO ES VITAL
                'users.name as waiter_name',
                DB::raw('COUNT(sales.id) as total_pedidos'),
                DB::raw('SUM(sales.total) as total_ventas'),
                DB::raw('AVG(sales.total) as ticket_promedio')
            )
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.restaurant_id', Filament::getTenant()->id)
            // ... resto de filtros ...
            ->groupBy('users.id', 'users.name') // Agrupa por ID también
            ->orderByDesc('total_ventas')
            ->get();
    }

    protected function getActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Descargar PDF')
                ->color('danger')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn() => $this->downloadPdf()),
        ];
    }

    public function downloadPdf()
    {
        $stats = $this->getStats();
        $data = $this->form->getState();

        $pdf = Pdf::loadView('pdf.pdf-ventas-mozo', [
            'stats' => $stats,
            'desde' => $data['fecha_desde'] ?? $this->fecha_desde,
            'hasta' => $data['fecha_hasta'] ?? $this->fecha_hasta,
            'restaurant' => Filament::getTenant()->name,
        ]);

        return response()->streamDownload(
            fn() => print($pdf->output()),
            "reporte-mozos-" . now()->format('Y-m-d') . ".pdf"
        );
    }

    public function descargarPdf($waiterId, $desde, $hasta)
    {
        $mozo = User::findOrFail($waiterId);

        $ventas = Sale::query()
            ->withoutGlobalScopes()
            ->where('user_id', $waiterId)
            ->where('restaurant_id', Filament::getTenant()->id)
            ->where('status', 'completado')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->latest('fecha_emision')
            ->get();

        $pdf = Pdf::loadView('pdf.reporte-ventas-mozo', [
            'mozo' => $mozo,
            'ventas' => $ventas,
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => $ventas->sum('total')
        ]);

        // Esto fuerza la descarga en el navegador sin salir de la página
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "reporte-{$mozo->name}-{$desde}.pdf");
    }

    public function aplicarFiltros()
    {
        Notification::make()->title('Reporte actualizado')->success()->send();
    }
}
