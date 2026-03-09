<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class UnitPolicy
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
        return $this->checkPermiso($user, 'listar_unidades_medida');
    }

    public function view(User $user, Unit $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_unidades_medida')) return false;

        // 🟢 SEGURIDAD AISLADA: La unidad (ej. Kg, Lt) debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_unidad_medida');
    }

    public function update(User $user, Unit $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_unidad_medida')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Unit $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_unidad_medida')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Unit $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Unit $model): bool
    {
        return false;
    }
}