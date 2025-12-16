<?php

namespace App\Filament\Restaurants\Resources\PurchaseResource\Widgets;

use App\Filament\Restaurants\Resources\PurchaseResource\Pages\ListPurchases;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseStats extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListPurchases::class;
    }
    
    protected function getStats(): array
    {
        $query = $this->getPageTableQuery()->where('estado_comprobante', 'aceptado'); // solo aceptadas

        return [
            Stat::make('Compras aceptadas', $query->count())
                ->icon('heroicon-o-clipboard-document')
                ->description('Cantidad de compras realizadas'),

            Stat::make('Total en compras', 'S/ ' . number_format($query->sum('total'), 2))
                ->icon('heroicon-o-clipboard-document')
                ->description('Suma de todos los montos de compras'),
        ];
    }
}
