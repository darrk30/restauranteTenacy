<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class StockAdjustmentPolicy
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
        return $this->checkPermiso($user, 'listar_ajustes_stock');
    }

    public function view(User $user, StockAdjustment $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_ajustes_stock')) return false;

        // 🟢 SEGURIDAD AISLADA: El ajuste de inventario debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_ajuste_stock');
    }

    public function update(User $user, StockAdjustment $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_ajuste_stock')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, StockAdjustment $model): bool
    {
        if (! $this->checkPermiso($user, 'anular_ajuste_stock')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, StockAdjustment $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, StockAdjustment $model): bool
    {
        return false;
    }
}