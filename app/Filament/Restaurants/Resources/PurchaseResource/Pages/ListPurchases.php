<?php

namespace App\Filament\Restaurants\Resources\PurchaseResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Restaurants\Resources\PurchaseResource;
use App\Filament\Restaurants\Resources\PurchaseResource\Widgets\PurchaseStats;
use App\Models\PaymentMethodPurchase;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    use ExposesTableToWidgets;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function eliminarPago($id)
    {
        $pago = PaymentMethodPurchase::find($id);

        if (! $pago) { return; }

        $purchase = $pago->purchase;
        $pago->delete();
        $totalPagado = $purchase->paymentMethods()->sum('monto');
        $saldo = max($purchase->total - $totalPagado, 0);
        $purchase->update([
            'saldo' => $saldo
        ]);
        $this->dispatch('$refresh');
        Notification::make()
            ->title('Pago eliminado correctamente')
            ->danger()
            ->send();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseStats::class,
        ];
    }

    // public function editarPago($id)
    // {
    //     $pago = \App\Models\PaymentMethodPurchase::find($id);

    //     if (!$pago) {
    //         return;
    //     }

    //     $this->pagoEditandose = $id;

    //     // form->fill requiere que el form esté dentro de la página
    //     $this->form->fill([
    //         'paymentMethods' => [
    //             [
    //                 'payment_method_id' => $pago->payment_method_id,
    //                 'monto' => $pago->monto,
    //                 'referencia' => $pago->referencia,
    //             ]
    //         ],
    //     ]);
    // }
}
