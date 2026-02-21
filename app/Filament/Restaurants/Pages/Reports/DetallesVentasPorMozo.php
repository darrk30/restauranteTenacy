<?php

namespace App\Filament\Restaurants\Pages\Reports;

use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Request;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Actions\Action;

class DetallesVentasPorMozo extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.reports.operativo.detalles-ventas-por-mozo';
    protected static ?string $title = 'Detalle de Ventas del Mozo';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'detalles-empleado-ventas';

    public $mozo;
    public $fecha_desde;
    public $fecha_hasta;

    public function mount()
    {
        $mozoId = Request::query('record');
        $this->fecha_desde = Request::query('desde', now()->startOfMonth()->startOfDay()->toDateTimeString());
        $this->fecha_hasta = Request::query('hasta', now()->endOfDay()->toDateTimeString());

        $this->mozo = Filament::getTenant()->users()->where('users.id', $mozoId)->first();

        if (!$this->mozo) {
            return redirect(\App\Filament\Restaurants\Pages\Reports\VentasPorMozo::getUrl());
        }
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "Ventas: " . ($this->mozo->name ?? 'Cargando...');
    }

    public function getBreadcrumbs(): array
    {
        return [
            \App\Filament\Restaurants\Pages\Reports\VentasPorMozo::getUrl() => 'Ranking de Ventas',
            null => 'Detalle de ' . ($this->mozo->name ?? 'Mozo'),
        ];
    }

    // 🟢 Agregamos el botón "Volver" como una acción nativa de Filament en la cabecera
    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver al Ranking')
                ->color('gray')
                ->icon('heroicon-m-arrow-left')
                ->url(\App\Filament\Restaurants\Pages\Reports\VentasPorMozo::getUrl())
        ];
    }

    // 🟢 CONSTRUCCIÓN DE LA TABLA NATIVA
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()
                    ->withoutGlobalScopes()
                    ->where('user_id', $this->mozo->id)
                    ->where('restaurant_id', Filament::getTenant()->id)
                    ->where('status', 'completado')
                    ->whereBetween('fecha_emision', [$this->fecha_desde, $this->fecha_hasta])
            )
            ->columns([
                TextColumn::make('cliente')
                    ->label('Cliente / Documento')
                    ->getStateUsing(fn(Sale $record) => $record->nombre_cliente ?? 'PÚBLICO GENERAL')
                    ->description(fn(Sale $record) => ($record->tipo_documento ?? 'DOC') . ': ' . ($record->numero_documento ?? '-------'))
                    ->searchable(['nombre_cliente', 'numero_documento']),

                TextColumn::make('comprobante')
                    ->label('Comprobante / Fecha')
                    ->getStateUsing(fn(Sale $record) => $record->tipo_comprobante . ' ' . $record->serie . '-' . $record->correlativo)
                    ->description(fn(Sale $record) => \Carbon\Carbon::parse($record->fecha_emision)->format('d/m/Y H:i'))
                    ->searchable(['serie', 'correlativo']),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn($state) => strtoupper($state))
                    ->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(2)
                    ->prefix('S/ ')
                    ->alignRight()
                    // 🟢 MAGIA DE FILAMENT: Suma automáticamente toda la consulta, sin importar la paginación
                    ->summarize(
                        Sum::make()
                            ->label('TOTAL ACUMULADO')
                            ->numeric(2)
                            ->prefix('S/ ')
                    ),
            ])
            ->defaultSort('fecha_emision', 'desc')
            ->striped() // Filas cebra
            ->paginated([25, 50, 100, 'all']); // Opciones de paginación
    }
}
