<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RestaurantPolicy
{
    use HandlesAuthorization;

    // ==========================================
    // EL ESCUDO DEL SUPER ADMIN
    // ==========================================
    // Se ejecuta antes que cualquier otro método. 
    // Si es el dueño del SaaS, se le permite todo automáticamente.
    public function before(User $user, $ability)
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
    }

    // ==========================================
    // REGLAS PARA LOS INQUILINOS (CLIENTES)
    // ==========================================

    /**
     * ¿Puede ver la lista general de restaurantes?
     */
    public function viewAny(User $user)
    {
        // Un usuario normal (Cajero, Mozo, Admin de local) solo debería 
        // tener acceso si pertenece al menos a un restaurante.
        return $user->restaurants()->exists();
    }

    /**
     * ¿Puede ver los detalles de un restaurante específico?
     */
    public function view(User $user, Restaurant $restaurant)
    {
        // Solo puede verlo si está vinculado a este restaurante en la tabla pivote
        return $user->restaurants->contains($restaurant->id);
    }

    /**
     * ¿Puede crear un nuevo restaurante desde el panel?
     */
    public function create(User $user)
    {
        // Retornamos false. Los clientes normales no pueden crear restaurantes 
        // desde dentro de su panel. Solo el Super Admin puede (pasó por el before).
        return false;
    }

    /**
     * ¿Puede editar la información de este restaurante?
     */
    public function update(User $user, Restaurant $restaurant)
    {
        // 1. Debe estar vinculado a este restaurante.
        // 2. Debe tener el permiso específico que creamos en el PermissionsRestSeeder.
        return $user->restaurants->contains($restaurant->id) && 
               $user->hasPermissionTo('editar_mi_restaurante');
    }

    /**
     * ¿Puede eliminar este restaurante?
     */
    public function delete(User $user, Restaurant $restaurant)
    {
        // Nadie puede eliminar un restaurante excepto el Super Admin.
        return false;
    }

    /**
     * ¿Puede restaurar un restaurante eliminado? (Si usas SoftDeletes)
     */
    public function restore(User $user, Restaurant $restaurant)
    {
        return false;
    }

    /**
     * ¿Puede forzar la eliminación permanente?
     */
    public function forceDelete(User $user, Restaurant $restaurant)
    {
        return false;
    }
}