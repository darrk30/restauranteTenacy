<?php

namespace App\Policies;

use App\Models\Printer;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PrinterPolicy
{
    // 🟢 1. PASE VIP: El Super Admin siempre tiene acceso total
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
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
        // Usa el nombre base que definiste en tu Seeder: 'listar_impresoras'
        return $this->checkPermiso($user, 'listar_impresoras');
    }

    public function view(User $user, Printer $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_impresoras')) {
            return false;
        }

        // 🟢 SEGURIDAD RESTAURANTE: Evita que un cliente vea la impresora de otro local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_impresora');
    }

    public function update(User $user, Printer $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_impresora')) {
            return false;
        }

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }

        return true;
    }

    public function delete(User $user, Printer $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_impresora')) {
            return false;
        }

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }

        return true;
    }

    public function restore(User $user, Printer $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Printer $model): bool
    {
        return false;
    }
}