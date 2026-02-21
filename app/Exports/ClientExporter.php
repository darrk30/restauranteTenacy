<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ClientExporter implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Client::with('typeDocument')
            ->where('restaurant_id', filament()->getTenant()->id)
            ->latest()
            ->get();
    }

    public function map($client): array
    {
        return [
            $client->typeDocument?->code,
            $client->numero,
            $client->nombres,
            $client->apellidos,
            $client->razon_social,
            $client->direccion,
            $client->email,
            $client->telefono,
        ];
    }

    public function headings(): array
    {
        return [
            'TIPO_DOCUMENTO',
            'NUMERO',
            'NOMBRE',
            'APELLIDO',
            'RAZON_SOCIAL',
            'DIRECCION',
            'CORREO',
            'TELEFONO',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => '000000']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'], // Amarillo
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
