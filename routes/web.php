<?php

use App\Http\Controllers\ImprimirController;
use App\Livewire\PedidoMesa;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/restaurants/{tenant}/imprimir-ticket-generico', [ImprimirController::class, 'imprimirTicket'])
    ->name('comanda.generica');
