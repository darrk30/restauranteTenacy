<?php

namespace App\Policies;

use App\Models\ConceptoCaja;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ConceptoCajaPolicy
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
        return $this->checkPermiso($user, 'listar_ingresos_egresos');
    }

    public function view(User $user, ConceptoCaja $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_ingresos_egresos')) return false;

        // 🟢 SEGURIDAD AISLADA: El concepto de caja debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ConceptoCaja $model): bool
    {
        return true;
    }

    public function delete(User $user, ConceptoCaja $model): bool
    {
        return true;
    }

    public function restore(User $user, ConceptoCaja $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, ConceptoCaja $model): bool
    {
        return false;
    }
}