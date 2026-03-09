<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PaymentMethodPolicy
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
        return $this->checkPermiso($user, 'listar_metodos_pago');
    }

    public function view(User $user, PaymentMethod $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_metodos_pago')) return false;

        // 🟢 SEGURIDAD AISLADA: Aseguramos que el Yape/Plin/Efectivo pertenezca a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_metodo_pago');
    }

    public function update(User $user, PaymentMethod $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_metodo_pago')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, PaymentMethod $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_metodo_pago')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, PaymentMethod $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, PaymentMethod $model): bool
    {
        return false;
    }
}