<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle; // 👈 Evita errores de nombre largo
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Filament\Facades\Filament;
use Carbon\Carbon;

class GananciasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithCustomStartCell, WithEvents, WithTitle
{
    use Exportable;

    protected $filters;
    protected $totales;

    public function __construct(array $filters)
    {
        $this->filters = $filters;

        // Pre-calculamos los totales para el footer
        $this->totales = $this->getQuery()->select(
            DB::raw('SUM(total) as ingresos'),
            DB::raw('SUM(costo_total) as costos')
        )->first();
    }

    public function title(): string
    {
        return 'Ganancias'; // 👈 Pestaña corta de Excel
    }

    public function getQuery()
    {
        $query = Sale::query()
            ->withoutGlobalScopes()
            ->where('restaurant_id', Filament::getTenant()->id)
            ->where('status', 'completado')
            ->select('sales.*', DB::raw('(total - costo_total) as ganancia_neta'))
            ->latest('fecha_emision');

        $data = $this->filters;

        if (!empty($data['fecha_desde'])) $query->whereDate('fecha_emision', '>=', $data['fecha_desde']);
        if (!empty($data['fecha_hasta'])) $query->whereDate('fecha_emision', '<=', $data['fecha_hasta']);
        if (!empty($data['tipo_comprobante'])) $query->where('tipo_comprobante', $data['tipo_comprobante']);

        return $query;
    }

    public function query()
    {
        return $this->getQuery();
    }

    // Empezamos en la fila 8 para dejar espacio arriba
    public function startCell(): string
    {
        return 'A8';
    }

    public function map($sale): array
    {
        $margen = $sale->total > 0 ? round(($sale->ganancia_neta / $sale->total) * 100, 1) : 0;

        return [
            Carbon::parse($sale->fecha_emision)->format('d/m/Y H:i'),
            $sale->serie . '-' . $sale->correlativo,
            $sale->total,
            $sale->costo_total,
            $sale->ganancia_neta,
            $margen . '%',
        ];
    }

    public function headings(): array
    {
        return [
            'FECHA Y HORA',
            'COMPROBANTE',
            'INGRESO (S/)',
            'COSTO REAL (S/)',
            'GANANCIA NETA (S/)',
            'MARGEN %',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            8 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A8A']], // Azul corporativo
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // --- CABECERA ---
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'REPORTE DE GANANCIAS Y RENTABILIDAD');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->mergeCells('A2:F2');
                $sheet->setCellValue('A2', 'Generado el: ' . now()->format('d/m/Y H:i:s'));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- FILTROS APLICADOS ---
                $f = $this->filters;
                $desde = isset($f['fecha_desde']) ? Carbon::parse($f['fecha_desde'])->format('d/m/Y H:i') : 'Inicio';
                $hasta = isset($f['fecha_hasta']) ? Carbon::parse($f['fecha_hasta'])->format('d/m/Y H:i') : 'Fin';

                // Obtenemos el nombre del comprobante si usa el Enum
                $comprobante = 'TODOS';
                if (!empty($f['tipo_comprobante'])) {
                    $comprobante = \App\Enums\DocumentSeriesType::tryFrom($f['tipo_comprobante'])?->label() ?? $f['tipo_comprobante'];
                }

                $textoFiltros = "Filtros aplicados:\n";
                $textoFiltros .= "• Fechas: {$desde} al {$hasta}\n";
                $textoFiltros .= "• Comprobante: " . strtoupper($comprobante);

                $sheet->mergeCells('A4:F6');
                $sheet->setCellValue('A4', $textoFiltros);
                $sheet->getStyle('A4')->getAlignment()->setWrapText(true);
                $sheet->getStyle('A4')->getFont()->setItalic(true);

                // --- FOOTER / TOTALES ---
                $lastRow = $sheet->getHighestRow() + 1;

                $ingresos = (float) $this->totales->ingresos;
                $costos = (float) $this->totales->costos;
                $ganancia = $ingresos - $costos;
                $margenGlobal = $ingresos > 0 ? round(($ganancia / $ingresos) * 100, 1) : 0;

                $sheet->setCellValue('B' . $lastRow, 'TOTALES ACUMULADOS:');
                $sheet->getStyle('B' . $lastRow)->getFont()->setBold(true);
                $sheet->getStyle('B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->setCellValue('C' . $lastRow, $ingresos);
                $sheet->setCellValue('D' . $lastRow, $costos);
                $sheet->setCellValue('E' . $lastRow, $ganancia);
                $sheet->setCellValue('F' . $lastRow, $margenGlobal . '%');

                $sheet->getStyle("C{$lastRow}:F{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FCD34D']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Formato Moneda Columnas C, D, E
                $sheet->getStyle('C9:E' . $lastRow)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            },
        ];
    }
}
