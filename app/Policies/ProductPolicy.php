<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ProductPolicy
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
        return $this->checkPermiso($user, 'listar_productos');
    }

    public function view(User $user, Product $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_productos')) return false;

        // 🟢 SEGURIDAD AISLADA: El producto (Ceviche, Gaseosa, etc.) debe pertenecer a la carta de este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_producto');
    }

    public function update(User $user, Product $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_producto')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Product $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_producto')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Product $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Product $model): bool
    {
        return false;
    }
}