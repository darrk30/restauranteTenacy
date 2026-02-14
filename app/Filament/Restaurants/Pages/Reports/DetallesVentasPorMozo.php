<?php

namespace App\Filament\Restaurants\Pages\Reports;

use Illuminate\Contracts\Support\Htmlable;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Request;

class DetallesVentasPorMozo extends Page
{
    protected string $view = 'filament.reports.operativo.detalles-ventas-por-mozo';
    protected static ?string $title = 'Detalle de Ventas del Mozo';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'detalles-empleado-ventas';

    public $mozo;
    public $ventas;
    public $fecha_desde;
    public $fecha_hasta;

    public function getBreadcrumbs(): array
    {
        return [
            VentasPorMozo::getUrl() => 'Ranking de Ventas',
            null => 'Detalle de ' . ($this->mozo->name ?? 'Mozo'),
        ];
    }

    public function getHeading(): string|Htmlable
    {
        return "Detalle de Ventas: " . ($this->mozo->name ?? 'Cargando...');
    }

    public function mount()
    {
        $mozoId = Request::query('record');
        $this->fecha_desde = Request::query('desde', now()->startOfMonth()->toDateString());
        $this->fecha_hasta = Request::query('hasta', now()->toDateString());

        $currentRestaurant = Filament::getTenant();

        // Buscamos al mozo vía relación pivot
        $this->mozo = $currentRestaurant->users()->where('users.id', $mozoId)->first();

        if (!$this->mozo) {
            return redirect(VentasPorMozo::getUrl());
        }

        // Consulta con relación de cliente
        $this->ventas = Sale::query()
            ->withoutGlobalScopes()
            ->where('user_id', $this->mozo->id)
            ->where('restaurant_id', Filament::getTenant()->id)
            ->where('status', 'completado')
            ->whereBetween('fecha_emision', [$this->fecha_desde, $this->fecha_hasta])
            ->latest('fecha_emision')
            ->get();
    }
}
