<?php

use App\Http\Controllers\ImprimirController;
use App\Livewire\PedidoMesa;
use App\Models\Sale;
use Illuminate\Support\Facades\Route;

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
