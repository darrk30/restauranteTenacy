<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class TablePolicy
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
        // En tu seeder no hay 'listar_mesas', así que usamos 'listar_pisos' 
        // ya que normalmente van juntos.
        return $this->checkPermiso($user, 'listar_pisos');
    }

    public function view(User $user, Table $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_pisos')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id; 
        }
        return true;
    }

    public function create(User $user): bool
    {
        // Usamos el nombre exacto de tu seeder
        return $this->checkPermiso($user, 'asignar_mesa');
    }

    public function update(User $user, Table $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_mesa')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id; 
        }
        return true;
    }

    public function delete(User $user, Table $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_mesa')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id; 
        }
        return true;
    }
}