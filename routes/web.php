<?php

use App\Http\Controllers\ImprimirController;
use App\Livewire\PedidoMesa;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get(
    '/restaurants/{tenant}/comanda/{order}',
    [ImprimirController::class, 'show']
)->name('comanda.pdf');
