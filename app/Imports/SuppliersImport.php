<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SuppliersImport implements ToModel, WithHeadingRow
{
    public int $proveedoresNuevos = 0;
    public int $proveedoresOmitidos = 0;
    public array $erroresDetalle = [];

    public function model(array $row)
    {
        // 1. Validar filas vacías
        if (empty($row['numero']) || empty($row['razon_social_o_nombre'])) {
            return null; 
        }

        $tenantId = filament()->getTenant()->id;
        $numero = $row['numero'];
        $nombre = $row['razon_social_o_nombre'];

        // 2. Verificar si el proveedor YA EXISTE
        $existe = Supplier::where('numero', $numero)
            ->where('restaurant_id', $tenantId)
            ->exists();

        if ($existe) {
            $this->proveedoresOmitidos++;
            $this->erroresDetalle[] = "[$nombre] Ya registrado";
            return null; // 🔴 Lo saltamos
        }

        // 3. Validación y limpieza de datos extra
        $tipoDoc = strtoupper(trim($row['tipo_documento'] ?? 'RUC'));
        
        $estadoRaw = strtolower(trim($row['estado'] ?? 'activo'));
        $estadoValido = in_array($estadoRaw, ['activo', 'inactivo', 'archivado']) ? $estadoRaw : 'activo';

        $this->proveedoresNuevos++;

        // 4. Crear el proveedor
        return new Supplier([
            'restaurant_id'  => $tenantId,
            'tipo_documento' => $tipoDoc,
            'numero'         => $numero,
            'name'           => $nombre,
            'correo'         => $row['correo'] ?? null,
            'telefono'       => $row['telefono'] ?? null,
            'direccion'      => $row['direccion'] ?? null,
            'departamento'   => $row['departamento'] ?? null,
            'status'         => $estadoValido,
        ]);
    }
}