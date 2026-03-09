<?php

namespace App\Policies;

use App\Models\Floor;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class FloorPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) return true;
        return null;
    }

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

    public function viewAny(User $user): bool
    {
        return $this->checkPermiso($user, 'listar_pisos');
    }

    public function view(User $user, Floor $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_pisos')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_piso');
    }

    public function update(User $user, Floor $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_piso')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Floor $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_piso')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }
}