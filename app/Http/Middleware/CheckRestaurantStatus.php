<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRestaurantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Obtenemos el restaurante (Tenant)
        $restaurant = Filament::getTenant();

        // 2. Verificamos si existe y si está inactivo
        if ($restaurant && $restaurant->status !== 'activo') {
            
            // 🟢 REDIRIGIMOS A LA VISTA BONITA
            return redirect()->route('suspendido');
        }

        // 3. Si está activo, lo dejamos pasar
        return $next($request);
    }
}