<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist; // 🟢 Necesario para el try/catch

class UserPolicy
{
    // 🟢 1. PASE VIP: Súper importante para que no te bloquees a ti mismo en el panel de Kipu
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        return null;
    }

    // 🟢 2. CORREGIDO: La lógica estaba invertida y el sufijo de tu Seeder era '_rest' (no '_pdv')
    private function getSuffix(): string
    {
        // Si hay Tenant (Restaurante), devuelve '_rest'. Si no hay Tenant (Panel Central), devuelve '_admin'
        return Filament::getTenant() ? '_rest' : '_admin';
    }

    // 🟢 3. ESCUDO ANTI-CRASH: Si olvidas crear un permiso, devuelve false en vez de romper la pantalla con un Error 500
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
        return $this->checkPermiso($user, 'listar_usuarios');
    }

    public function view(User $user, User $model): bool
    {
        // 1. Permiso básico
        if (! $this->checkPermiso($user, 'listar_usuarios')) {
            return false;
        }

        // 2. SEGURIDAD RESTAURANTE (Tenant Isolation)
        if ($this->getSuffix() === '_rest') {
            
            // Obtenemos los IDs de los restaurantes del Usuario Logueado
            $misrestaurantsIds = $user->restaurants()->pluck('restaurants.id');

            // Verificamos si el usuario objetivo ($model) pertenece a alguna de esos restaurantes
            $esCompanero = $model->restaurants()
                ->whereIn('restaurants.id', $misrestaurantsIds)
                ->exists();

            // Si NO es mi compañero de sucursal -> NO LO PUEDO VER
            if (! $esCompanero) {
                return false;
            }
        }

        return true;
    }

    public function create(User $user): bool
    {
        // 🟢 CORREGIDO: En tu Seeder lo llamaste 'crear_usuario_rest' (en singular)
        return $this->checkPermiso($user, 'crear_usuario');
    }

    public function update(User $user, User $model): bool
    {
        // 1. Permiso 🟢 CORREGIDO al singular de tu Seeder
        if (! $this->checkPermiso($user, 'editar_usuario')) {
            return false;
        }

        // 2. SEGURIDAD RESTAURANTE
        if ($this->getSuffix() === '_rest') {
            
            // A. No permitir editar a Super Admins
            if ($model->hasRole('Super Admin') || $model->hasRole('Admin')) {
                return false;
            }

            // B. Verificar que pertenezca a mis restaurantes
            $misrestaurantsIds = $user->restaurants()->pluck('restaurants.id');
            $esCompanero = $model->restaurants()
                ->whereIn('restaurants.id', $misrestaurantsIds)
                ->exists();

            if (! $esCompanero) {
                return false;
            }
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        // Seguridad: No auto-eliminarse
        if ($user->id === $model->id) {
            return false;
        }

        // 🟢 CORREGIDO al singular de tu Seeder
        if (! $this->checkPermiso($user, 'eliminar_usuario')) {
            return false;
        }

        // SEGURIDAD RESTAURANTE
        if ($this->getSuffix() === '_rest') {
            
            // A. No borrar admins globales
            if ($model->hasRole('Super Admin')) {
                return false;
            }

            // B. Solo borrar empleados de mi sucursal
            $misrestaurantsIds = $user->restaurants()->pluck('restaurants.id');
            $esCompanero = $model->restaurants()
                ->whereIn('restaurants.id', $misrestaurantsIds)
                ->exists();

            if (! $esCompanero) {
                return false;
            }
        }

        return true;
    }

    public function restore(User $user, User $model): bool
    {
        try {
            return $user->hasPermissionTo('restaurar_usuarios_admin');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function forceDelete(User $user, User $model): bool
    {
        try {
            return $user->hasPermissionTo('eliminar_usuarios_admin');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }
}