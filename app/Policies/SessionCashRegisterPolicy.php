<?php

namespace App\Policies;

use App\Models\SessionCashRegister;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class SessionCashRegisterPolicy
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
        return $this->checkPermiso($user, 'listar_apertura_cierre');
    }

    public function view(User $user, SessionCashRegister $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_apertura_cierre')) return false;

        // 🟢 SEGURIDAD AISLADA: La sesión debe pertenecer a este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        // Nota: Si en tu seeder se llama 'abrir_caja', cámbialo aquí
        return $this->checkPermiso($user, 'aperturar_caja');
    }

    public function update(User $user, SessionCashRegister $model): bool
    {
        return $this->checkPermiso($user, 'cerrar_caja');
    }

    public function delete(User $user, SessionCashRegister $model): bool
    {
        return true;
    }

    public function restore(User $user, SessionCashRegister $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, SessionCashRegister $model): bool
    {
        return false;
    }
}