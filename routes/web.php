<?php

use App\Http\Controllers\ImprimirController;
use App\Livewire\PedidoMesa;
use App\Models\Category;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/app/{tenant}/imprimir-ticket-generico', [ImprimirController::class, 'imprimirTicket'])
    ->name('comanda.generica');

Route::get('/imprimir/comanda/{order}', [ImprimirController::class, 'imprimirComanda'])
    ->name('imprimir.comanda');

Route::get('/sales/print-ticket/{sale}', [ImprimirController::class, 'printTicket'])
    ->name('sales.print.ticket');

Route::get('/sale/print/{sale}', function (Sale $sale) {
    return view('pdf.ticket-venta', [
        'sale' => $sale,
        'tenant' => $sale->restaurant,
    ]);
})->name('sale.ticket.print');

Route::get('/precuenta/{order}', function (\App\Models\Order $order) {
    $order->load(['details', 'table.floor', 'user', 'restaurant']);
    return view('pdf.precuenta-ticket', ['order' => $order]);
})->name('order.precuenta.print');

Route::get('/ingresar', function () {
    return view('auth.login');
})->name('login');

Route::domain('{tenant:slug}.' . env('APP_DOMAIN', 'restaurantetenacy.test'))->group(function () {

    Route::get('/carta', function (\App\Models\Restaurant $tenant) {

        // 1. Validaciones de estado
        if ($tenant->carta_activa_admin !== 'activo') {
            return response("<div style='font-family: sans-serif; text-align: center; padding: 40px; color: #666;'><h2>Servicio no habilitado</h2></div>");
        }

        if ($tenant->carta_activa_cliente !== 'activo') {
            return response("<div style='font-family: sans-serif; text-align: center; padding: 40px; color: #666;'><h2>Menú pausado</h2></div>");
        }

        // 2. Obtener Banners (Promociones)
        // Mapeamos para que coincida con lo que espera el componente x-slider
        $promociones = \App\Models\Banner::where('restaurant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($b) => [
                'type' => $b->type,
                'bg_color' => $b->bg_color,
                'title' => $b->title,
                'image' => asset('storage/' . $b->image),
                'image_mobile' => $b->image_mobile ? asset('storage/' . $b->image_mobile) : null,
                'link' => $b->link,
            ]);

        // 3. Obtener Categorías
        $categorias = \App\Models\Category::where('restaurant_id', $tenant->id)
            ->where('status', true) // Asumiendo que tienes este campo
            ->get();

        // 4. Obtener Productos "aplanados" para el Grid y el Buscador
        // Traemos los productos activos del restaurante
        // 4. Obtener Productos con relación Muchos a Muchos
        $productos = \App\Models\Product::where('restaurant_id', $tenant->id)
            ->where('status', 'activo')
            ->with('categories')
            ->get()
            ->map(fn($p) => [
                'name' => $p->name,
                'category' => $p->categories->first()->name ?? 'general',
                'price' => (float) $p->price,
                // Usamos descripción como descripción corta si no tienes un campo short
                'description' => Str::limit($p->description, 40) ?? '1 un.',
                'long_description' => $p->description,
                'badge' => $p->badge ?? null, // Si no tienes la columna, usa null o un texto fijo

                // --- AQUÍ ESTÁ EL ARREGLO ---
                // Extraemos de 'image_path' y lo guardamos como 'image'
                'image' => $p->image_path ? asset('storage/' . $p->image_path) : null,

                'gallery' => $p->gallery
                    ? collect($p->gallery)->map(fn($img) => asset('storage/' . $img))->toArray()
                    : [($p->image_path ? asset('storage/' . $p->image_path) : null)],
            ]);

        return view('catalogo.carta-digital', [
            'tenant' => $tenant,
            'promociones' => $promociones,
            'categorias' => $categorias,
            'productos' => $productos
        ]);
    })->name('carta.digital');
});
