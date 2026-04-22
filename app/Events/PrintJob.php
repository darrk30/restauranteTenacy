<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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
        // El 'api_token' es lo que garantiza que el mensaje 
        // viaje por un "túnel" único para ese cliente.
        return new Channel('impresora.' . $this->venta['api_token']);
    }

    public function broadcastAs()
    {
        return 'nuevo-ticket';
    }
}
