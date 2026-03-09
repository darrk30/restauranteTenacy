<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class ClientPolicy
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
        return $this->checkPermiso($user, 'listar_clientes');
    }

    public function view(User $user, Client $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_clientes')) return false;

        // 🟢 SEGURIDAD AISLADA: El cliente debe pertenecer a la base de datos de este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_cliente');
    }

    public function update(User $user, Client $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_cliente')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Client $model): bool
    {
        // Como no tienes 'eliminar_cliente' en tu seeder, el try/catch devolverá false
        // ocultando el botón de eliminar de forma elegante y segura.
        if (! $this->checkPermiso($user, 'eliminar_cliente')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Client $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Client $model): bool
    {
        return false;
    }
}