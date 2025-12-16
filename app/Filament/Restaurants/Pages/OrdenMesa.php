<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;

class OrdenMesa extends Page
{
    protected static string $view = 'filament.pdv.orden-mesa';
    protected static string $panel = 'restaurants';

    public int $mesa;

    public function mount(int $mesa)
    {
        $this->mesa = $mesa;
    }

    public static function getSlug(): string
    {
        return 'orden-mesa/{mesa}';
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
