<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class VentasCanalExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithCustomStartCell, WithEvents
{
    use Exportable;

    protected $filters;
    protected $totalAmount = 0; // Para guardar el total calculado

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        $this->totalAmount = $this->getQuery()->sum('total');
    }

    // Método auxiliar para reutilizar la lógica de filtros
    public function getQuery()
    {
        $query = Sale::query()->latest('fecha_emision');
        $data = $this->filters;

        if (!empty($data['fecha_desde'])) $query->whereDate('fecha_emision', '>=', $data['fecha_desde']);
        if (!empty($data['fecha_hasta'])) $query->whereDate('fecha_emision', '<=', $data['fecha_hasta']);
        if (!empty($data['canal'])) $query->where('canal', $data['canal']);
        if (!empty($data['serie'])) $query->where('serie', $data['serie']);
        if (!empty($data['numero'])) $query->where('correlativo', 'like', "%{$data['numero']}%");

        return $query;
    }

    public function query()
    {
        return $this->getQuery();
    }

    // 1. INDICAMOS QUE LA TABLA DE DATOS EMPIECE EN LA FILA 8
    // (Dejamos las filas 1-7 libres para el título y filtros)
    public function startCell(): string
    {
        return 'A8';
    }

    public function map($sale): array
    {
        return [
            $sale->fecha_emision->format('d/m/Y H:i'),
            $sale->tipo_comprobante,
            $sale->serie . '-' . $sale->correlativo,
            $sale->nombre_cliente,
            strtoupper($sale->canal),
            $sale->total, // Pasamos el número puro para que Excel pueda sumar si quiere
            ucfirst($sale->status),
        ];
    }

    public function headings(): array
    {
        return [
            'FECHA EMISIÓN',
            'TIPO COMP.',
            'DOCUMENTO',
            'CLIENTE',
            'CANAL',
            'TOTAL (S/)',
            'ESTADO',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la cabecera de la tabla (Fila 8)
            8 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    // AQUÍ OCURRE LA MAGIA: Escribimos Título, Filtros y Totales
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                
                // --- SECCIÓN SUPERIOR (HEADER) ---
                
                // Título Principal
                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'REPORTE DETALLADO DE VENTAS POR CANAL');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Fecha de Generación
                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2', 'Generado el: ' . now()->format('d/m/Y H:i:s'));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Mostrar Filtros Aplicados (Filas 4, 5, 6)
                $f = $this->filters;
                $textoFiltros = "Filtros aplicados:\n";
                $textoFiltros .= "• Fechas: " . ($f['fecha_desde'] ?? 'Inicio') . " al " . ($f['fecha_hasta'] ?? 'Fin') . "\n";
                $textoFiltros .= "• Canal: " . ($f['canal'] ? strtoupper($f['canal']) : 'TODOS') . "\n";
                if(!empty($f['serie'])) $textoFiltros .= "• Serie: " . $f['serie'];

                $sheet->mergeCells('A4:G6');
                $sheet->setCellValue('A4', $textoFiltros);
                $sheet->getStyle('A4')->getAlignment()->setWrapText(true); // Permitir saltos de línea
                $sheet->getStyle('A4')->getFont()->setItalic(true);

                // --- SECCIÓN INFERIOR (FOOTER / TOTALES) ---

                // Buscamos la última fila con datos + 1
                $lastRow = $sheet->getHighestRow() + 1;

                // Escribimos la etiqueta "TOTAL GENERAL"
                $sheet->setCellValue('E' . $lastRow, 'TOTAL GENERAL:');
                $sheet->getStyle('E' . $lastRow)->getFont()->setBold(true);
                $sheet->getStyle('E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Escribimos el Monto Total
                $sheet->setCellValue('F' . $lastRow, $this->totalAmount);
                $sheet->getStyle('F' . $lastRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FCD34D']], // Color amarillo claro
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
                
                // Formato de moneda para la columna F (desde fila 9 hasta el final)
                $sheet->getStyle('F9:F' . $lastRow)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            },
        ];
    }
}