<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class RolePolicy
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
        return $this->checkPermiso($user, 'listar_roles');
    }

    public function view(User $user, Role $role): bool
    {
        if (! $this->checkPermiso($user, 'ver_rol')) return false;

        // 🟢 SEGURIDAD AISLADA: El Rol debe pertenecer al local actual
        // (Ajusta 'restaurant_id' si en tu tabla de roles usas 'team_id' u otro nombre)
        if ($tenant = Filament::getTenant()) {
            return $role->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_rol');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $this->checkPermiso($user, 'editar_rol')) return false;

        if ($tenant = Filament::getTenant()) {
            return $role->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Role $role): bool
    {
        // Basado en tu arreglo, usas 'anular_rol' para eliminar
        if (! $this->checkPermiso($user, 'anular_rol')) return false;

        if ($tenant = Filament::getTenant()) {
            return $role->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }
}