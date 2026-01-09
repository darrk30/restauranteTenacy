<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Pages\Page;
use App\Models\Floor;
use Filament\Actions\Action;
use Filament\Facades\Filament;

class PointOfSale extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'Punto de venta';
    protected static ?string $navigationGroup = 'Punto de venta';
    protected static ?string $title = 'Punto de venta';

    protected static string $view = 'filament.pdv.point-of-sale';


    protected function getViewData(): array
    {
        return [
            'floors' => Floor::with('tables')->get(),
            'tenant' => Filament::getTenant(),
        ];
    }

    public function iniciarAtencion($mesaId, $personas)
    {
        session()->flash('personas_iniciales', $personas);
        $tenantId = Filament::getTenant()->slug; 
        return redirect()->to("/restaurants/{$tenantId}/orden-mesa/{$mesaId}");
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
