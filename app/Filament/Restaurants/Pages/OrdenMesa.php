<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;

class OrdenMesa extends Page
{
    protected static string $view = 'filament.pdv.orden-mesa';
    protected static string $panel = 'restaurants';

    public int $mesa;
    public ?int $pedido = null;

    public function mount(int $mesa, ?int $pedido = null)
    {
        $this->mesa = $mesa;
        $this->pedido = $pedido;
    }

    public static function getSlug(): string
    {
        return 'orden-mesa/{mesa}/{pedido?}';
    }


    public function getHeading(): string
    {
        return '';
    }

    public function getViewData(): array
    {
        return [
            'tenant'   => Filament::getTenant(), // aquí sí es objeto
            'mesa'     => $this->mesa,
            'pedido' => $this->pedido,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
