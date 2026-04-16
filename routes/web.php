<?php

use App\Http\Controllers\CartaController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ImprimirController;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pdv/{tenant}/imprimir-ticket-generico', [ImprimirController::class, 'imprimirTicket'])
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
    // 🟢 Cargamos las relaciones, pero filtramos los detalles para excluir los cancelados
    $order->load([
        'details' => function ($query) {
            $query->where('status', '!=', \App\Enums\StatusPedido::Cancelado);
        },
        'table.floor', 
        'user', 
        'restaurant'
    ]);
    
    return view('pdf.precuenta-ticket', ['order' => $order]);
})->name('order.precuenta.print');

Route::domain('{tenant:slug}.' . env('APP_DOMAIN', 'restaurantetenacy.test'))->group(function () {
    Route::get('/carta', [CartaController::class, 'index'])->name('carta.digital');
    Route::post('/carta/procesar-pedido', [CartaController::class, 'procesarPedido'])->name('carta.procesar');
    Route::post('/carta/procesar-wsp', [CartaController::class, 'procesarPedidoSoloWsp'])->name('carta.procesar.wsp');
});

Route::get('/notas/{nota}/print-ticket', [PrintController::class, 'printNota'])
    ->name('notas.print.ticket')
    ->middleware('auth');

Route::get('/cuenta-suspendida', function () {
    return view('filament.suspendido');
})->name('suspendido');