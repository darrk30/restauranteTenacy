<?php

namespace App\Filament\Restaurants\Pages;

use Filament\Pages\Page;
use App\Models\Floor;
use App\Models\Order; // <--- AGREGAR ESTO
use Filament\Actions\Action;
use Filament\Facades\Filament;

class PointOfSale extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'Punto de venta';
    protected static ?string $navigationGroup = 'Punto de venta';
    protected static ?string $title = 'Punto de venta';

    protected static string $view = 'filament.pdv.point-of-sale';

    // === AGREGAR ESTAS PROPIEDADES ===
    public $mostrarModalComanda = false;
    public ?Order $ordenGenerada = null;

    // === AGREGAR EL MÉTODO MOUNT ===
    public function mount()
    {
        // Verificar si venimos de una anulación (OrdenMesa nos manda 'print_order_id')
        if (session()->has('print_order_id')) {
            $idOrden = session('print_order_id');
            
            // Cargamos la orden con todas las relaciones necesarias para determinar áreas
            $this->ordenGenerada = Order::with(['details.product.production.printer', 'table', 'user'])
                                        ->find($idOrden);
            
            if ($this->ordenGenerada) {
                $this->mostrarModalComanda = true;
            }
        }
    }

    public function cerrarModalComanda()
    {
        $this->mostrarModalComanda = false;
        session()->forget('print_job_id');
        session()->forget('print_order_id');
    }

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