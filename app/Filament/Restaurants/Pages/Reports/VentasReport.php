<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Exports\VentasExport;
use App\Models\Sale;
use App\Models\PaymentMethod;
use App\Filament\Restaurants\Widgets\VentasStatsWidget;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\ViewEntry;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class VentasReport extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;
    use ExposesTableToWidgets;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Reporte de Ventas';
    protected static ?string $title = 'Análisis de Ventas';
    protected static string $view = 'filament.reports.ventas.ventas-report';
    public ?string $activeTab = null;

    protected function getTableQuery(): Builder
    {
        return Sale::query()
            ->with(['movements.paymentMethod', 'user', 'order'])
            ->latest('fecha_emision');
    }

    protected function getHeaderWidgets(): array
    {
        return [VentasStatsWidget::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->headerActions([
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
                                'mozo'                    => 'Mozo / Atendió',
                                'monto_especifico_filtro' => 'Monto por Método (Filtro)',
                                'op_gravada'              => 'Op. Gravada',
                                'monto_igv'               => 'IGV',
                                'monto_descuento'         => 'Descuento Aplicado',
                                'total'                   => 'Monto Total',
                                'status'                  => 'Estado',
                                'notas'                   => 'Notas/Observaciones',
                            ])
                            ->default(['fecha_emision', 'comprobante', 'nombre_cliente', 'total', 'status'])
                            ->columns(3)
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        $filtrosRaw = $livewire->tableFilters;
                        $filtrosAplicados = [];

                        // 1. Traducir filtros para el encabezado del Excel
                        if (!empty($filtrosRaw['fecha_emision']['desde'])) $filtrosAplicados['Desde'] = $filtrosRaw['fecha_emision']['desde'];
                        if (!empty($filtrosRaw['fecha_emision']['hasta'])) $filtrosAplicados['Hasta'] = $filtrosRaw['fecha_emision']['hasta'];
                        if (!empty($filtrosRaw['payment_method_id']['value'])) {
                            $filtrosAplicados['Método'] = \App\Models\PaymentMethod::find($filtrosRaw['payment_method_id']['value'])?->name;
                        }
                        if (!empty($filtrosRaw['status']['value'])) $filtrosAplicados['Estado'] = ucfirst($filtrosRaw['status']['value']);

                        // 2. FORZAR ORDEN DE COLUMNAS
                        // Definimos aquí el orden exacto en el que queremos que aparezcan en el Excel
                        $ordenMaestro = [
                            'fecha_emision',
                            'tipo_comprobante',
                            'comprobante',
                            'orden_codigo',
                            'nombre_cliente',
                            'documento_identidad',
                            'mozo',
                            'monto_especifico_filtro',
                            'op_gravada',
                            'monto_igv',
                            'monto_descuento',
                            'total',
                            'status',
                            'notas',
                        ];

                        // Filtramos el orden maestro para que solo contenga lo que el usuario seleccionó
                        $columnasOrdenadas = array_values(array_intersect($ordenMaestro, $data['columnas_reporte']));

                        return Excel::download(
                            new VentasExport($query, $columnasOrdenadas, $filtrosAplicados),
                            'reporte-ventas-' . now()->format('d-m-Y') . '.xlsx'
                        );
                    })
            ])
            ->columns([
                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Fecha/Hora')
                    // Mostramos la fecha como valor principal
                    ->dateTime('d/m/Y')
                    ->icon('heroicon-m-calendar')
                    ->iconColor('gray')
                    // Mostramos la hora como descripción (aparece abajo en gris)
                    ->description(function ($record) {
                        return '🕒 ' . $record->fecha_emision->format('H:i A');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn($record) => "{$record->serie}-{$record->correlativo}")
                    ->searchable(['serie', 'correlativo']),

                Tables\Columns\TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->description(fn($record) => "{$record->tipo_documento}: {$record->numero_documento}")
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Mozo')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order.code')
                    ->label('Pedido')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('monto_especifico_filtro')
                    ->label(function () {
                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                        $nombre = $metodoId ? PaymentMethod::find($metodoId)?->name : 'Método';
                        return "Recaudado ({$nombre})";
                    })
                    ->money('PEN')
                    ->color('success')
                    ->state(function ($record) {
                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                        if (!$metodoId) return 0;
                        return $record->movements
                            ->where('payment_method_id', $metodoId)
                            ->where('status', 'aprobado')
                            ->sum('monto');
                    })
                    ->visible(fn() => !empty($this->tableFilters['payment_method_id']['value'])),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->weight('bold')
                    ->color(fn($record) => $record->status === 'anulado' ? 'gray' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'completado' => 'success',
                        'anulado' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha_emision')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q, $date) => $q->whereDate('fecha_emision', '>=', $date))
                            ->when($data['hasta'], fn($q, $date) => $q->whereDate('fecha_emision', '<=', $date));
                    })
                    ->default(['desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->toDateString()]),

                Tables\Filters\SelectFilter::make('payment_method_id')
                    ->label('Método de Pago')
                    ->options(PaymentMethod::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!$data['value']) return $query;
                        return $query->whereHas('movements', fn($q) => $q->where('payment_method_id', $data['value']));
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options(['completado' => 'Completado', 'anulado' => 'Anulado']),
            ])
            ->actions([
                Tables\Actions\Action::make('detalles')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->modalHeading('Detalle de Venta')
                    ->modalSubmitAction(false)
                    ->infolist(fn(Infolist $infolist) => $infolist->schema([
                        ViewEntry::make('detalle_venta')->view('filament.reports.ventas.venta-detalle')->columnSpanFull()
                    ])),
            ]);
    }
}
