<?php

namespace App\Policies;

use App\Models\DocumentSerie;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class DocumentSeriePolicy
{
    // 🟢 PASE VIP: Súper Admin tiene control total
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) return true;
        return null;
    }

    // 🟢 SUFIJO DINÁMICO
    private function getSuffix(): string
    {
        return Filament::getTenant() ? '_rest' : '_admin';
    }

    // 🟢 ESCUDO ANTI-CRASH
    private function checkPermiso(User $user, string $permisoBase): bool
    {
        $permisoCompleto = $permisoBase . $this->getSuffix();
        try {
            return $user->hasPermissionTo($permisoCompleto);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function viewAny(User $user): bool
    {
        return $this->checkPermiso($user, 'listar_series_documentos');
    }

    public function view(User $user, DocumentSerie $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_series_documentos')) return false;

        // 🟢 SEGURIDAD AISLADA: La serie (ej. F001, B001) debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_serie_documento');
    }

    public function update(User $user, DocumentSerie $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_serie_documento')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, DocumentSerie $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_serie_documento')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, DocumentSerie $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, DocumentSerie $model): bool
    {
        return false;
    }
}