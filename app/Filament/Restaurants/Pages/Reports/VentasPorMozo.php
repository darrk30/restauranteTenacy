<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Actions\Action as TableAction;

class VentasPorMozo extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Ventas por Mozo';
    protected static ?string $title = 'Rendimiento de Mozos';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 80;
    protected static string $view = 'filament.reports.operativo.ventas-por-mozo';

    public $filter_type = 'mensual';
    public $fecha_desde;
    public $fecha_hasta;

    public function mount()
    {
        $this->fecha_desde = now()->subDays(30)->startOfDay()->toDateTimeString();
        $this->fecha_hasta = now()->endOfDay()->toDateTimeString();

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
                        ->required()
                        ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                        ->live(),

                    DateTimePicker::make('fecha_hasta')
                        ->label('Hasta')
                        ->required()
                        ->hidden(fn(Get $get) => $get('filter_type') !== 'personalizado')
                        ->live(),
                ]),
        ];
    }

    // 🟢 METODO DE TABLA NATIVA FILAMENT
    public function table(Table $table): Table
    {
        $desde = $this->form->getState()['fecha_desde'] ?? $this->fecha_desde;
        $hasta = $this->form->getState()['fecha_hasta'] ?? $this->fecha_hasta;

        return $table
            ->query(
                Sale::query()
                    ->withoutGlobalScopes()
                    ->select(
                        'users.id as id', // 🟢 ESTA ES LA SOLUCIÓN (Le da a Filament la llave que busca)
                        'users.id as waiter_id',
                        'users.name as waiter_name',
                        DB::raw('COUNT(sales.id) as total_pedidos'),
                        DB::raw('SUM(sales.total) as total_ventas'),
                        DB::raw('AVG(sales.total) as ticket_promedio')
                    )
                    ->join('users', 'sales.user_id', '=', 'users.id')
                    ->where('sales.restaurant_id', Filament::getTenant()->id)
                    ->where('sales.status', 'completado')
                    ->whereBetween('sales.fecha_emision', [$desde, $hasta])
                    ->groupBy('users.id', 'users.name')
                    ->orderByDesc('total_ventas')
            )
            ->columns([
                TextColumn::make('waiter_name')
                    ->label('Colaborador')
                    // 🟢 SOLUCIÓN: Le indicamos que busque en la columna real 'users.name'
                    ->searchable(query: fn($query, $search) => $query->where('users.name', 'like', "%{$search}%"))
                    ->weight('bold')
                    ->icon('heroicon-o-user')
                    ->url(fn($record) => \App\Filament\Restaurants\Pages\Reports\DetallesVentasPorMozo::getUrl([
                        'record' => $record->waiter_id,
                        'desde' => $desde,
                        'hasta' => $hasta
                    ]))
                    ->color('primary'),

                TextColumn::make('total_pedidos')
                    ->label('Servicios')
                    ->alignCenter()
                    ->summarize(Sum::make()->label('Total Servicios')),

                TextColumn::make('ticket_promedio')
                    ->label('Ticket Prom.')
                    ->numeric(2)
                    ->prefix('S/ ')
                    ->alignRight(),

                TextColumn::make('total_ventas')
                    ->label('Total Ventas')
                    ->numeric(2)
                    ->prefix('S/ ')
                    ->alignRight()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(
                        Sum::make()
                            ->label('TOTAL GENERAL')
                            ->numeric(2)
                            ->prefix('S/ ')
                    ),
            ])
            ->actions([
                // Botón individual de descarga PDF para cada fila
                TableAction::make('descargar_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->button()
                    ->action(fn($record) => $this->descargarPdf($record->waiter_id, $desde, $hasta))
            ])
            ->striped()
            ->paginated([10, 25, 50, 'all']);
    }

    protected function getActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Descargar Resumen PDF')
                ->color('danger')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn() => $this->downloadPdfGlobal()),
        ];
    }

    // Renombramos esto para que no choque con la descarga individual
    public function downloadPdfGlobal()
    {
        $desde = $this->form->getState()['fecha_desde'] ?? $this->fecha_desde;
        $hasta = $this->form->getState()['fecha_hasta'] ?? $this->fecha_hasta;

        // Reutilizamos la consulta base
        $stats = Sale::query()
            ->withoutGlobalScopes()
            ->select(
                'users.id as waiter_id',
                'users.name as waiter_name',
                DB::raw('COUNT(sales.id) as total_pedidos'),
                DB::raw('SUM(sales.total) as total_ventas'),
                DB::raw('AVG(sales.total) as ticket_promedio')
            )
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.restaurant_id', Filament::getTenant()->id)
            ->where('sales.status', 'completado')
            ->whereBetween('sales.fecha_emision', [$desde, $hasta])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_ventas')
            ->get();

        $pdf = Pdf::loadView('pdf.pdf-ventas-mozo', [
            'stats' => $stats,
            'desde' => $desde,
            'hasta' => $hasta,
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
            ->where('restaurant_id', \Filament\Facades\Filament::getTenant()->id)
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

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "reporte-{$mozo->name}-{$desde}.pdf");
    }

    public function aplicarFiltros()
    {
        Notification::make()->title('Reporte actualizado')->success()->send();
    }
}
