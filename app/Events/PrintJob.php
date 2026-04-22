<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJob implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $venta;

    // Pasamos todo el objeto de la venta (que ya incluye el restaurant_id)
    public function __construct($venta)
    {
        $this->venta = $venta;
    }

    public function broadcastOn()
    {
        // MAGIA MULTI-TENANT: El canal ahora incluye el ID del restaurante
        // Ejemplo: 'impresora.restaurante.5'
        return new Channel('impresora.restaurante.' . $this->venta['restaurant_id']);
    }

    public function broadcastAs()
    {
        return 'nuevo-ticket';
    }
}