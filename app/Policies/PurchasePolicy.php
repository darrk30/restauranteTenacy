<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PurchasePolicy
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
        return $this->checkPermiso($user, 'listar_compras');
    }

    public function view(User $user, Purchase $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_compras')) return false;

        // 🟢 SEGURIDAD AISLADA: La compra debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_compra');
    }

    public function update(User $user, Purchase $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_compra')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Purchase $model): bool
    {
        // 🟢 ATENCIÓN AQUÍ: Usamos 'anular_compra' porque así lo definiste en tu Seeder
        if (! $this->checkPermiso($user, 'anular_compra')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Purchase $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Purchase $model): bool
    {
        return false;
    }
}