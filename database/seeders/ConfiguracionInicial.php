<?php

namespace Database\Seeders;

use App\Enums\DocumentSeriesType;
use App\Models\Client;
use App\Models\DocumentSerie;
use App\Models\PaymentMethod;
use App\Models\Production;
use App\Models\Restaurant;
use App\Models\TypeDocument;
use App\Models\CashRegister; // IMPORTANTE: Agregar el modelo
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConfiguracionInicial extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    }

    public function runForRestaurant(Restaurant $restaurant): void
    {
        // 1. Buscar el ID del tipo de documento DNI por su código
        $dniType = TypeDocument::where('code', 'DNI')->first();

        // 2. Crear Cliente "Clientes Varios" por defecto
        if ($dniType) {
            Client::firstOrCreate([
                'restaurant_id'    => $restaurant->id,
                'numero'           => '99999999',
            ], [
                'nombres'          => 'CLIENTES',
                'apellidos'        => 'VARIOS',
                'type_document_id' => $dniType->id,
                'status'           => 'Activo',
            ]);
        }

        // 3. MÉTODOS DE PAGO POR DEFECTO CON IMÁGENES
        $metodosPago = [
            [
                'name' => 'Efectivo',
                'payment_condition' => 'Contado',
                'requiere_referencia' => false,
                'image' => 'metodos_pago/efectivo.png',
            ],
            [
                'name' => 'Yape',
                'payment_condition' => 'Contado',
                'requiere_referencia' => true,
                'image' => 'metodos_pago/yape.png',
            ],
            [
                'name' => 'Tarjeta',
                'payment_condition' => 'Contado',
                'requiere_referencia' => true,
                'image' => 'metodos_pago/tarjeta.png',
            ],
        ];

        foreach ($metodosPago as $metodo) {
            PaymentMethod::firstOrCreate([
                'restaurant_id' => $restaurant->id,
                'name'          => $metodo['name'],
            ], [
                'payment_condition'   => $metodo['payment_condition'],
                'requiere_referencia' => $metodo['requiere_referencia'],
                'image_path'          => $metodo['image'],
                'status'              => true,
            ]);
        }

        // 4. PUNTOS DE PRODUCCIÓN (Cocina y Almacén)
        $puntosProduccion = ['Cocina', 'Almacén'];

        foreach ($puntosProduccion as $punto) {
            Production::firstOrCreate([
                'name'          => $punto,
                'restaurant_id' => $restaurant->id,
            ], [
                'status'        => true,
                'printer_id'    => null,
            ]);
        }

        // 5. CAJA PRINCIPAL (Nueva sección agregada)
        CashRegister::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'code'          => 'CAJA-01', // Código único para la caja
        ], [
            'name'          => 'Caja Principal',
            'status'        => true,
        ]);

        // 6. SERIES DE DOCUMENTOS
        $seriesIniciales = [
            [
                'type_documento' => DocumentSeriesType::FACTURA,
                'serie' => 'F001',
            ],
            [
                'type_documento' => DocumentSeriesType::BOLETA,
                'serie' => 'B001',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_VENTA,
                'serie' => 'NV01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_CREDITO,
                'serie' => 'FC01',
            ],
            [
                'type_documento' => DocumentSeriesType::NOTA_CREDITO,
                'serie' => 'BC01',
            ],
        ];

        foreach ($seriesIniciales as $data) {
            DocumentSerie::firstOrCreate([
                'restaurant_id' => $restaurant->id,
                'serie' => $data['serie'],
            ], [
                'type_documento' => $data['type_documento'],
                'current_number' => 0,
                'is_active' => true,
            ]);
        }
    }
}
