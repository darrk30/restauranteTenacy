<?php

namespace App\Policies;

use App\Models\UnitCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class UnitCategoryPolicy
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
        return $this->checkPermiso($user, 'listar_categoria_unidades');
    }

    public function view(User $user, UnitCategory $unitCategory): bool
    {
        if (! $this->checkPermiso($user, 'listar_categoria_unidades')) return false;

        // 🟢 SEGURIDAD AISLADA: La categoría debe pertenecer al restaurante actual
        if ($tenant = Filament::getTenant()) {
            return $unitCategory->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_categoria_unidad');
    }

    public function update(User $user, UnitCategory $unitCategory): bool
    {
        if (! $this->checkPermiso($user, 'editar_unidad_categoria')) return false;

        if ($tenant = Filament::getTenant()) {
            return $unitCategory->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, UnitCategory $unitCategory): bool
    {
        // 💡 Usando el nombre exacto que me pasaste (eliminar_eliminar_unidad)
        if (! $this->checkPermiso($user, 'eliminar_eliminar_unidad')) return false;

        if ($tenant = Filament::getTenant()) {
            return $unitCategory->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, UnitCategory $unitCategory): bool
    {
        return false;
    }

    public function forceDelete(User $user, UnitCategory $unitCategory): bool
    {
        return false;
    }
}