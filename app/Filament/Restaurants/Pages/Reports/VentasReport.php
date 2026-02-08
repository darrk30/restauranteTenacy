<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Sale;
use App\Models\PaymentMethod;
use App\Filament\Restaurants\Widgets\VentasStatsWidget;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column as ExcelColumn;

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
            ->with(['movements']) // <--- ESTO ES CLAVE: Carga los movimientos de un solo golpe
            ->latest('fecha_emision');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VentasStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->headerActions([
                // Botón de Exportación
                ExportAction::make()
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->exports([
                        ExcelExport::make()
                            ->withFilename('Reporte_Ventas_' . date('d-m-Y'))
                            // IMPORTANTE: Quitamos fromTable() y definimos manualmente para control total
                            ->withColumns([
                                ExcelColumn::make('fecha_emision')
                                    ->heading('Fecha')
                                    ->format('dd/mm/yyyy hh:mm'),

                                ExcelColumn::make('comprobante')
                                    ->heading('Documento'),

                                ExcelColumn::make('nombre_cliente')
                                    ->heading('Cliente'),

                                ExcelColumn::make('monto_especifico_filtro')
                                    ->heading(function () {
                                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                                        $nombre = $metodoId ? \App\Models\PaymentMethod::find($metodoId)?->name : 'Método';
                                        return "Recaudado en {$nombre}";
                                    })
                                    ->formatStateUsing(function ($record) {
                                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                                        if (!$metodoId) return 0;

                                        return \App\Models\CashRegisterMovement::query()
                                            ->where('referencia_id', $record->id)
                                            ->where('referencia_type', \App\Models\Sale::class)
                                            ->where('payment_method_id', $metodoId)
                                            ->where('status', 'aprobado')
                                            ->sum('monto') ?? 0;
                                    })
                                    ->format('#,##0.00 "PEN"'), // Formato de moneda explícito

                                ExcelColumn::make('total')
                                    ->heading('Total General')
                                    ->format('#,##0.00 "PEN"'),

                                ExcelColumn::make('status')
                                    ->heading('Estado')
                                    ->formatStateUsing(fn($state) => ucfirst($state)),
                            ])
                    ])
            ])
            ->columns([
                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Fecha/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn($record) => "{$record->serie}-{$record->correlativo}")
                    ->searchable(['serie', 'correlativo']),

                Tables\Columns\TextColumn::make('nombre_cliente')
                    ->label('Cliente')
                    ->searchable(),

                // Dentro de table() -> columns() en VentasReport.php

                Tables\Columns\TextColumn::make('monto_especifico_filtro')
                    ->label(function () {
                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                        $nombre = $metodoId ? \App\Models\PaymentMethod::find($metodoId)?->name : 'Método';
                        return "Recaudado en {$nombre}";
                    })
                    ->money('PEN')
                    ->weight('bold')
                    ->color('success')
                    ->state(function ($record) {
                        $metodoId = $this->tableFilters['payment_method_id']['value'] ?? null;
                        if (!$metodoId) return 0;

                        // ACCESO DIRECTO A BASE DE DATOS PARA EVITAR ERRORES DE COLECCIÓN
                        return \App\Models\CashRegisterMovement::query()
                            ->where('referencia_id', $record->id)
                            ->where('referencia_type', \App\Models\Sale::class)
                            ->where('payment_method_id', $metodoId)
                            ->where('status', 'aprobado')
                            ->sum('monto') ?? 0;
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
                // 📅 Rango de fechas
                Tables\Filters\Filter::make('fecha_emision')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn($q, $date) => $q->whereDate('fecha_emision', '>=', $date)
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn($q, $date) => $q->whereDate('fecha_emision', '<=', $date)
                            );
                    })
                    ->default([
                        'desde' => now()->startOfMonth()->toDateString(),
                        'hasta' => now()->toDateString(),
                    ]),

                // 🧾 Tipo comprobante
                Tables\Filters\SelectFilter::make('tipo_comprobante')
                    ->options([
                        'Boleta' => 'Boleta',
                        'Factura' => 'Factura',
                        'Nota de Venta' => 'Nota de Venta',
                    ]),

                // ⚙ Estado
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'completado' => 'Completado',
                        'anulado' => 'Anulado',
                    ]),

                // 💳 Método de pago (CORREGIDO)
                Tables\Filters\SelectFilter::make('payment_method_id')
                    ->label('Método de Pago')
                    ->options(
                        PaymentMethod::pluck('name', 'id')->toArray()
                    )
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas('movements', function ($q) use ($value) {
                            $q->where('payment_method_id', $value);
                        });
                    }),

            ])
            ->actions([
                Tables\Actions\Action::make('detalles')
                    ->label('Detalles')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->modalHeading('Desglose de Venta')
                    ->modalSubmitAction(false)
                    ->infolist(fn(Infolist $infolist) => $infolist->schema([
                        Section::make('Cuentas Totales')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('op_gravada')->label('Op. Gravada')->money('PEN'),
                                TextEntry::make('monto_igv')->label('IGV')->money('PEN'),
                                TextEntry::make('descuento_total')->label('Descuento')->money('PEN')->color('danger'),
                                TextEntry::make('total')->label('Total')->money('PEN')->weight('bold')->color('primary'),
                            ]),

                        Section::make('Detalle de Ítems')
                            ->schema([
                                RepeatableEntry::make('details')
                                    ->schema([
                                        TextEntry::make('product_name')->label('Producto'),
                                        TextEntry::make('cantidad')->label('Cant.'),
                                        TextEntry::make('precio_unitario')->money('PEN'),
                                        TextEntry::make('subtotal')->money('PEN'),
                                    ])
                                    ->columns(4),
                            ]),

                        Section::make('Métodos de Pago')
                            ->schema([
                                RepeatableEntry::make('movements')
                                    ->schema([
                                        TextEntry::make('paymentMethod.name')->label('Método'),
                                        TextEntry::make('monto')->money('PEN'),
                                        TextEntry::make('observacion')->label('Nota'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])),
            ]);
    }
}
