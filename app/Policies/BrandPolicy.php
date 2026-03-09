<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class BrandPolicy
{
    // ==========================================
    // 🟢 PASE VIP: Súper Admin tiene control total
    // ==========================================
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) return true;
        return null;
    }

    // ==========================================
    // 🟢 SUFIJO DINÁMICO Y ESCUDO ANTI-CRASH
    // ==========================================
    private function getSuffix(): string
    {
        return Filament::getTenant() ? '_rest' : '_admin';
    }

    private function checkPermiso(User $user, string $permisoBase): bool
    {
        $permisoCompleto = $permisoBase . $this->getSuffix();
        try {
            return $user->hasPermissionTo($permisoCompleto);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    // ==========================================
    // 🟢 MÉTODOS DE LA POLICY
    // ==========================================

    public function viewAny(User $user): bool
    {
        return $this->checkPermiso($user, 'listar_marcas');
    }

    public function view(User $user, Brand $brand): bool
    {
        if (! $this->checkPermiso($user, 'listar_marcas')) return false;

        if ($tenant = Filament::getTenant()) {
            return $brand->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_marca');
    }

    public function update(User $user, Brand $brand): bool
    {
        if (! $this->checkPermiso($user, 'editar_marca')) return false;

        if ($tenant = Filament::getTenant()) {
            return $brand->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Brand $brand): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_marca')) return false;

        if ($tenant = Filament::getTenant()) {
            return $brand->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Brand $brand): bool
    {
        return false;
    }

    public function forceDelete(User $user, Brand $brand): bool
    {
        return false;
    }
}