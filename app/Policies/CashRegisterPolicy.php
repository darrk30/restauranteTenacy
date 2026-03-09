<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class CashRegisterPolicy
{
    // 🟢 1. PASE VIP: El Super Admin siempre tiene acceso
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) return true;
        return null;
    }

    // 🟢 2. SUFIJO DINÁMICO
    private function getSuffix(): string
    {
        return Filament::getTenant() ? '_rest' : '_admin';
    }

    // 🟢 3. ESCUDO ANTI-CRASH
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
        // Usa el permiso exacto de tu seeder
        return $this->checkPermiso($user, 'listar_cajas_registradoras');
    }

    public function view(User $user, CashRegister $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_cajas_registradoras')) return false;

        // 🟢 SEGURIDAD: Comprueba que la caja sea de este restaurante
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_caja_registradora');
    }

    public function update(User $user, CashRegister $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_caja_registradora')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, CashRegister $model): bool
    {
        // Si más adelante decides agregar 'eliminar_caja_registradora_rest' a tu seeder,
        // este método ya estará listo para usarlo. Si no existe, bloquea el botón.
        if (! $this->checkPermiso($user, 'eliminar_caja_registradora')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, CashRegister $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, CashRegister $model): bool
    {
        return false;
    }
}