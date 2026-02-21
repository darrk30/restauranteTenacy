<?php

namespace App\Exports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SuppliersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Supplier::where('restaurant_id', filament()->getTenant()->id)
            ->latest()
            ->get();
    }

    public function map($supplier): array
    {
        return [
            $supplier->tipo_documento,
            $supplier->numero,
            $supplier->name, // Mapeado a "RAZON_SOCIAL_O_NOMBRE"
            $supplier->correo,
            $supplier->telefono,
            $supplier->direccion,
            $supplier->departamento,
            ucfirst($supplier->status),
        ];
    }

    public function headings(): array
    {
        return [
            'TIPO_DOCUMENTO',
            'NUMERO',
            'RAZON_SOCIAL_O_NOMBRE',
            'CORREO',
            'TELEFONO',
            'DIRECCION',
            'DEPARTAMENTO',
            'ESTADO',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => '000000']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'], // 🟡 Fondo Amarillo
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ],
        ];
    }
}