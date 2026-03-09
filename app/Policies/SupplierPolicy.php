<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SupplierPolicy
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
        return $this->checkPermiso($user, 'listar_proveedores');
    }

    public function view(User $user, Supplier $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_proveedores')) return false;

        // 🟢 SEGURIDAD AISLADA: El proveedor debe pertenecer a la agenda de este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_proveedor');
    }

    public function update(User $user, Supplier $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_proveedor')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Supplier $model): bool
    {
        if (! $this->checkPermiso($user, 'anular_proveedor')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Supplier $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Supplier $model): bool
    {
        return false;
    }
}