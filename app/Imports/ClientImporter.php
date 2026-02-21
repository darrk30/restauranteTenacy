<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\TypeDocument;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientImporter implements ToModel, WithHeadingRow
{
    // 🟢 Creamos variables públicas para guardar el reporte
    public array $yaRegistrados = [];
    public int $importadosNuevos = 0;

    public function model(array $row)
    {
        // Validación básica de fila vacía
        if (empty($row['numero']) || empty($row['tipo_documento'])) {
            return null;
        }

        // Buscar el ID del tipo de documento
        $tipoDoc = TypeDocument::where('code', strtoupper($row['tipo_documento']))->first();
        if (!$tipoDoc) {
            return null;
        }

        $tenantId = filament()->getTenant()->id;

        // 🟢 Verificamos si el cliente YA EXISTE en la base de datos
        $existe = Client::where('numero', $row['numero'])
            ->where('restaurant_id', $tenantId)
            ->exists();

        if ($existe) {
            // Extraemos un nombre o razón social para avisarle al usuario quién es
            $nombreAviso = $row['nombre'] ?? $row['razon_social'] ?? $row['numero'];
            $this->yaRegistrados[] = $nombreAviso;

            return null; // 🔴 Devolvemos null para que el Excel no lo procese ni modifique
        }

        // Si no existe, aumentamos el contador y lo creamos
        $this->importadosNuevos++;

        return new Client([
            'numero' => $row['numero'],
            'restaurant_id' => $tenantId,
            'type_document_id' => $tipoDoc->id,
            'nombres' => $row['nombre'] ?? null,
            'apellidos' => $row['apellido'] ?? null,
            'razon_social' => $row['razon_social'] ?? null,
            'direccion' => $row['direccion'] ?? null,
            'email' => $row['correo'] ?? null,
            'telefono' => $row['telefono'] ?? null,
        ]);
    }
}
