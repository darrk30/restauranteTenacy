<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PromotionPolicy
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
        return $this->checkPermiso($user, 'listar_promociones');
    }

    public function view(User $user, Promotion $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_promociones')) return false;

        // 🟢 SEGURIDAD AISLADA: La promoción (2x1, descuento, etc.) debe ser de este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_promocion');
    }

    public function update(User $user, Promotion $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_promocion')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Promotion $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_promocion')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Promotion $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Promotion $model): bool
    {
        return false;
    }
}