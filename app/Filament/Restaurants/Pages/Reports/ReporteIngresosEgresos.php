<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\ConceptoCaja;
use App\Models\User;
use App\Models\CashRegister; // 🟢 Importar modelo de Cajas
use App\Filament\Restaurants\Widgets\IngresosEgresosStats;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

class ReporteIngresosEgresos extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Ingresos y Egresos';
    protected static ?string $title = 'Reporte de Ingresos y Egresos';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 65;
    protected static string $view = 'filament.reports.cajas.reporte-ingresos-egresos';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fecha_desde' => now()->startOfDay()->toDateTimeString(),
            'fecha_hasta' => now()->endOfDay()->toDateTimeString(),
        ]);
    }

    // Filtro dinámico: actualiza los widgets al cambiar cualquier valor del formulario
    public function updated($name, $value)
    {
        if (str_starts_with($name, 'data')) {
            $this->dispatch('update-ingresos-egresos-stats', filters: $this->data);
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IngresosEgresosStats::make(['filters' => $this->data]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarPdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $data = $this->prepararDatosPdf();
                    $pdf = Pdf::loadView('pdf.reporte-ingresos-egresos', $data);

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        "Reporte_Caja_" . now()->format('d-m-Y_His') . ".pdf"
                    );
                }),
        ];
    }

    private function prepararDatosPdf(): array
    {
        $filtros = $this->data;
        $tenant = Filament::getTenant();

        $query = ConceptoCaja::query()
            ->with(['user', 'personal', 'sessionCashRegister.cashRegister']) // 🟢 Carga relación de caja
            ->where('estado', 'aprobado');

        // Aplicar filtros comunes
        $query = $this->aplicarFiltros($query, $filtros);

        $movimientos = $query->latest()->get();
        $totalIngresos = $movimientos->where('tipo_movimiento', 'ingreso')->sum('monto');
        $totalEgresos = $movimientos->where('tipo_movimiento', 'egreso')->sum('monto');

        return [
            'movimientos' => $movimientos,
            'ingresos' => $totalIngresos,
            'egresos' => $totalEgresos,
            'balance' => $totalIngresos - $totalEgresos,
            'filtros' => [
                'Desde' => !empty($filtros['fecha_desde']) ? Carbon::parse($filtros['fecha_desde'])->format('d/m/Y H:i') : 'Inicio',
                'Hasta' => !empty($filtros['fecha_hasta']) ? Carbon::parse($filtros['fecha_hasta'])->format('d/m/Y H:i') : 'Fin',
                'Tipo' => !empty($filtros['tipo_movimiento']) ? ucfirst($filtros['tipo_movimiento']) : 'Todos',
                'Caja' => !empty($filtros['cash_register_id']) ? CashRegister::find($filtros['cash_register_id'])?->name : 'Todas', // 🟢 Para el título del PDF
            ],
            'restaurant' => $tenant->name,
            'fecha_emision' => now()->format('d/m/Y h:i A'),
        ];
    }

    // Función auxiliar para no repetir código de filtrado
    private function aplicarFiltros($query, $filtros)
    {
        if (!empty($filtros['fecha_desde'])) $query->where('created_at', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta'])) $query->where('created_at', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['tipo_movimiento'])) $query->where('tipo_movimiento', $filtros['tipo_movimiento']);
        if (!empty($filtros['usuario_id'])) $query->where('usuario_id', $filtros['usuario_id']);

        // 🟢 Filtro por Caja (Accediendo a través de la sesión)
        if (!empty($filtros['cash_register_id'])) {
            $query->whereHas('sessionCashRegister', function ($q) use ($filtros) {
                $q->where('cash_register_id', $filtros['cash_register_id']);
            });
        }
        return $query;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Filtros de Búsqueda')->schema([
                Grid::make(5)->schema([ // Aumentado a 5 para que quepa el nuevo filtro
                    DateTimePicker::make('fecha_desde')->label('Desde')->live(),
                    DateTimePicker::make('fecha_hasta')->label('Hasta')->live(),
                    Select::make('cash_register_id') // 🟢 Nuevo Filtro de Caja
                        ->label('Caja')
                        ->options(CashRegister::pluck('name', 'id'))
                        ->placeholder('Todas las cajas')
                        ->live(),
                    Select::make('tipo_movimiento')
                        ->label('Tipo')
                        ->options(['ingreso' => 'Ingresos', 'egreso' => 'Egresos'])
                        ->placeholder('Todos')->live(),
                    Select::make('usuario_id')->label('Cajero')
                        ->options(User::pluck('name', 'id'))
                        ->placeholder('Todos')->live(),
                ])
            ])->collapsible()
        ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = ConceptoCaja::query()
                    ->with(['user', 'personal', 'sessionCashRegister.cashRegister'])
                    ->where('estado', 'aprobado');

                return $this->aplicarFiltros($query, $this->data);
            })
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('sessionCashRegister.cashRegister.name')
                    ->label('Caja')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('tipo_movimiento')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => $state === 'ingreso' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('beneficiario')
                    ->label('Persona / Beneficiario')
                    ->getStateUsing(function (ConceptoCaja $record) {
                        if ($record->personal_id !== null) {
                            return ($record->personal?->name ?? 'Personal') . ' (Personal)';
                        }
                        return $record->persona_externa ?? '-';
                    })
                    ->icon(fn($record) => $record->personal_id ? 'heroicon-m-user-circle' : 'heroicon-m-user-group')
                    ->color(fn($record) => $record->personal_id ? 'primary' : 'gray'),

                // 🟢 COLUMNA MOTIVO AGREGADA
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->description(fn(ConceptoCaja $record): string => $record->categoria?->getLabel() ?? '') // Opcional: muestra la categoría abajo
                    ->wrap()
                    ->searchable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->weight('bold')
                    ->color(fn($record) => $record->tipo_movimiento === 'ingreso' ? 'success' : 'danger')
                    ->summarize(
                        Sum::make()
                            ->label('Balance Neto')
                            ->using(fn($query) => $query->selectRaw("SUM(CASE WHEN tipo_movimiento = 'ingreso' THEN monto ELSE -monto END)")->value('aggregate') ?? 0)
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
