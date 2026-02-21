<?php

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

namespace App\Filament\Restaurants\Resources\ClientResource\Pages;

use App\Filament\Restaurants\Resources\ClientResource;
use App\Models\Sale;
use Filament\Resources\Pages\ContentPage; // O Page normal
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewSaleDetail extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ClientResource::class;
    protected static string $view = 'filament.clientes.view-sale-detail';
    protected static ?string $title = 'Detalle de Venta';

    public $sale;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $saleId = request()->query('sale');

        // 🟢 Cargamos 'movements.paymentMethod' en lugar de un 'paymentMethod' inexistente
        $this->sale = Sale::with([
            'details',
            'movements.paymentMethod'
        ])->findOrFail($saleId);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ClientResource::getUrl('index') => 'Clientes',
            ClientResource::getUrl('edit', ['record' => $this->record]) => $this->record->nombres ?? $this->record->razon_social,
            ClientResource::getUrl('facturas', ['record' => $this->record]) => 'Facturas',
            '#' => "Detalle " . $this->sale->serie . "-" . $this->sale->correlativo,
        ];
    }
}
