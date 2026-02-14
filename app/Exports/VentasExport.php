<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class VentasExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    protected $query;
    protected $columnasSeleccionadas;
    protected $filtros;
    protected $nombresColumnas;

    public function __construct($query, $columnasSeleccionadas, $filtros = [])
    {
        $this->query = $query;
        $this->columnasSeleccionadas = $columnasSeleccionadas;
        $this->filtros = $filtros;

        $this->nombresColumnas = [
            'fecha_emision'       => 'FECHA EMISIÓN',
            'nombre_cliente'      => 'CLIENTE',
            'documento_identidad' => 'DOCUMENTO IDENTIDAD',
            'comprobante'         => 'COMPROBANTE',
            'total'               => 'TOTAL (S/)',
            'status'              => 'ESTADO',
            'monto_especifico_filtro' => 'MONTO POR MÉTODO',
            'orden_codigo'        => 'COD. PEDIDO',
            'mozo'                => 'MOZO',
            'tipo_comprobante'    => 'TIPO COMP.',
            'notas'               => 'NOTAS',
            'op_gravada'          => 'GRAVADA',
            'monto_igv'           => 'IGV',
            'monto_descuento'     => 'DESCUENTO',
        ];
    }

    public function query() { return $this->query; }

    public function headings(): array
    {
        $textoFiltros = "FILTROS: " . (empty($this->filtros) ? "General" : json_encode($this->filtros));
        $headerTabla = array_map(fn($col) => $this->nombresColumnas[$col] ?? strtoupper($col), $this->columnasSeleccionadas);

        return [
            ['REPORTE DETALLADO DE VENTAS'],
            [$textoFiltros],
            ['Descargado el: ' . now()->format('d/m/Y H:i')],
            [],
            $headerTabla
        ];
    }

    public function map($venta): array
    {
        $fila = [];
        foreach ($this->columnasSeleccionadas as $col) {
            $fila[] = match ($col) {
                'fecha_emision'     => $venta->fecha_emision ? \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i') : '',
                'documento_identidad' => "{$venta->tipo_documento}: {$venta->numero_documento}",
                'comprobante'       => "{$venta->serie}-{$venta->correlativo}",
                'total'             => (float) $venta->total,
                'monto_especifico_filtro' => (float) $this->calcularMontoMetodo($venta),
                'orden_codigo'      => $venta->order?->code ?? 'N/A',
                'mozo'              => $venta->user?->name ?? 'Sistema',
                'status'            => strtoupper($venta->status),
                'op_gravada'        => (float) $venta->op_gravada,
                'monto_igv'         => (float) $venta->monto_igv,
                'monto_descuento'   => (float) $venta->monto_descuento,
                default             => $venta->{$col},
            };
        }
        return $fila;
    }

    protected function calcularMontoMetodo($venta)
    {
        $metodoNombre = $this->filtros['Método'] ?? null;
        return (float) $venta->movements
            ->where('status', 'aprobado')
            ->when($metodoNombre, fn($c) => $c->filter(fn($m) => optional($m->paymentMethod)->name == $metodoNombre))
            ->sum('monto');
    }

    public function columnFormats(): array
    {
        $formatos = [];
        $monedaCols = ['total', 'monto_especifico_filtro', 'op_gravada', 'monto_igv', 'monto_descuento'];
        foreach ($this->columnasSeleccionadas as $index => $col) {
            if (in_array($col, $monedaCols)) {
                $formatos[Coordinate::stringFromColumnIndex($index + 1)] = '"S/" #,##0.00_-';
            }
        }
        return $formatos;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            5 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2E75B6']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();
                $totalRow = $highestRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                foreach ($this->columnasSeleccionadas as $index => $col) {
                    if (in_array($col, ['total', 'monto_especifico_filtro'])) {
                        $letra = Coordinate::stringFromColumnIndex($index + 1);
                        $sheet->setCellValue($letra . $totalRow, "=SUM({$letra}6:{$letra}{$highestRow})");
                        $sheet->getStyle($letra . $totalRow)->getFont()->setBold(true);
                    }
                }
            },
        ];
    }
}