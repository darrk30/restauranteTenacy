<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

trait ActualizarFile
{
    /**
     * ¡OJO AQUÍ! 
     * El nombre debe ser boot + NombreDelTrait.
     * Si tu trait es ActualizarFile, esto debe ser bootActualizarFile.
     */
    protected static function bootActualizarFile(): void
    {
        // 1. AL ACTUALIZAR (Reemplazo)
        static::updating(function (Model $model) {
            foreach ($model->getFileFields() as $field) {
                // Verificamos si el campo cambió
                if ($model->isDirty($field)) {
                    // Obtenemos la ruta ANTERIOR (la que está en la BD antes de guardar)
                    $oldFile = $model->getOriginal($field);

                    // Si existía y es diferente a la nueva, borrarla
                    if ($oldFile && $oldFile !== $model->{$field}) {
                        Storage::disk('public')->delete($oldFile);
                    }
                }
            }
        });

        // 2. AL ELIMINAR (Limpieza total)
        static::deleting(function (Model $model) {
            foreach ($model->getFileFields() as $field) {
                if ($model->{$field}) {
                    Storage::disk('public')->delete($model->{$field});
                }
            }
        });
    }

    /**
     * Define qué campos son archivos.
     */
    public function getFileFields(): array
    {
        // Si definiste $fileFields en el modelo, usa eso
        if (property_exists($this, 'fileFields')) {
            return $this->fileFields;
        }

        // Por defecto asume 'image_path'
        return ['image_path'];
    }
}