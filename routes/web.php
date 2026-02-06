<?php

use App\Http\Controllers\ImprimirController;
use App\Livewire\PedidoMesa;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/restaurants/{tenant}/imprimir-ticket-generico', [ImprimirController::class, 'imprimirTicket'])
    ->name('comanda.generica');

Route::get('/imprimir/comanda/{order}', [ImprimirController::class, 'imprimirComanda'])
    ->name('imprimir.comanda');

Route::get('/sales/print-ticket/{sale}', [ImprimirController::class, 'printTicket'])
    ->name('sales.print.ticket');
