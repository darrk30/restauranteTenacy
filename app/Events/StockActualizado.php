<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
        // No enviamos datos para mantener el paquete ligero. 
        // El cliente simplemente volverá a consultar sus datos al recibir esto.
    }

    public function broadcastOn(): array
    {
        // Usamos un canal público llamado 'inventario'
        return [
            new Channel('inventario'),
        ];
    }
}