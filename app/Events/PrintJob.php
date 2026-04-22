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
        // El canal ahora será algo como: impresora.token_secreto_123
        return new PrivateChannel('impresora.' . $this->venta['api_token']);
    }

    public function broadcastAs()
    {
        return 'nuevo-ticket';
    }
}
