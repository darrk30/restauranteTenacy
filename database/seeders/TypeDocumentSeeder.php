<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\TypeDocument;
use Illuminate\Database\Seeder;

class TypeDocumentSeeder extends Seeder
{
    public function run(): void
    {
        
    }

    public function runForRestaurant(Restaurant $restaurant): void
    {
        $documents = [
            [
                'code' => 'DNI',
                'description' => 'Documento Nacional de Identidad',
                'status' => true,
                'maximo_carateres' => 8, // DNI siempre es 8
            ],
            [
                'code' => 'RUC',
                'description' => 'Registro Único de Contribuyentes',
                'status' => true,
                'maximo_carateres' => 11, // RUC siempre es 11
            ],
        ];

        foreach ($documents as $doc) {
            TypeDocument::firstOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'code' => $doc['code']
                ],
                [
                    'description' => $doc['description'],
                    'status' => $doc['status'],
                    'maximo_carateres' => $doc['maximo_carateres'], 
                ]
            );
        }
    }
}