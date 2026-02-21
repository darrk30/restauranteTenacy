<?php

namespace App\Filament\Restaurants\Resources\SupplierResource\Pages;

use App\Filament\Restaurants\Resources\SupplierResource;
use App\Models\Purchase;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ViewPurchaseDetail extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Detalle de Compra';

    // Ruta a la vista personalizada que crearemos
    protected static string $view = 'filament.proveedores.view-purchase-detail';

    public $purchase;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $purchaseId = request()->query('purchase');

        // Cargamos solo las relaciones que existen en tus modelos:
        // details, details.product y paymentMethods.paymentMethod
        $this->purchase = \App\Models\Purchase::with([
            'details.product',
            'details.variant',
            'paymentMethods.paymentMethod',
            // 'user' // Descomenta solo si agregaste la relación arriba
        ])
            ->where('supplier_id', $this->record->id)
            ->findOrFail($purchaseId);
    }

    // Definimos las migas de pan (Breadcrumbs)
    public function getBreadcrumbs(): array
    {
        // Creamos la etiqueta del comprobante uniendo serie y número
        $comprobante = "{$this->purchase->serie}-{$this->purchase->numero}";

        return [
            SupplierResource::getUrl('index') => 'Proveedores',
            SupplierResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            SupplierResource::getUrl('compras', ['record' => $this->record]) => 'Compras',
            '#' => "Detalle de Compra: " . $comprobante,
        ];
    }
}
