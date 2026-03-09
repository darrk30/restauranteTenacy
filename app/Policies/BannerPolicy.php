<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class BannerPolicy
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
        return $this->checkPermiso($user, 'listar_banners');
    }

    public function view(User $user, Banner $model): bool
    {
        if (! $this->checkPermiso($user, 'listar_banners')) return false;

        // 🟢 SEGURIDAD AISLADA: El banner debe pertenecer a la carta digital de este local
        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $this->checkPermiso($user, 'crear_banner');
    }

    public function update(User $user, Banner $model): bool
    {
        if (! $this->checkPermiso($user, 'editar_banner')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function delete(User $user, Banner $model): bool
    {
        if (! $this->checkPermiso($user, 'eliminar_banner')) return false;

        if ($tenant = Filament::getTenant()) {
            return $model->restaurant_id === $tenant->id;
        }
        return true;
    }

    public function restore(User $user, Banner $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Banner $model): bool
    {
        return false;
    }
}