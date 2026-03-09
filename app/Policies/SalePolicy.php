<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SalePolicy
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
        return $this->checkPermiso($user, 'listar_historial_ventas');
    }

    public function view(User $user, Sale $model): bool
    {
        if (! $this->checkPermiso($user, 'ver_detalle_venta')) return false;

        // 🟢 SEGURIDAD AISLADA: La compra debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sale $model): bool
    {
        // 🟢 ATENCIÓN AQUÍ: Usamos 'anular_compra' porque así lo definiste en tu Seeder
        if (! $this->checkPermiso($user, 'anular_venta')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sale $sale): bool
    {
        return false;
    }
}
