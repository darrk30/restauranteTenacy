<?php

namespace App\Policies;

use App\Models\Production;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ProductionPolicy
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
        return $this->checkPermiso($user, 'listar_areas_produccion');
    }

    public function view(User $user, Production $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_areas_produccion')) return false;

        // 🟢 SEGURIDAD AISLADA: La cocina o barra debe pertenecer a esta sucursal
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_area_produccion');
    }

    public function update(User $user, Production $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_area_produccion')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Production $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_area_produccion')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Production $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Production $model): bool
    {
        return false;
    }
}