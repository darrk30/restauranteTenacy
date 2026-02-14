<?php

namespace App\Filament\Restaurants\Resources\PurchaseResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Restaurants\Resources\PurchaseResource;
use App\Traits\ManjoStockProductos;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchase extends EditRecord
{
    use ManjoStockProductos;

    protected static string $resource = PurchaseResource::class;

    /** Control interno */
    protected bool $shouldApplyStock = false;
    protected bool $alreadyReversed = false;

    /** Snapshot original */
    protected ?string $estadoOriginal = null;
    protected array $itemsOriginales = [];

    /** Movimiento generado */
    protected ?string $movimiento = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Carga snapshot original desde BD
     */
    protected function loadOriginalSnapshot(): void
    {
        if ($this->estadoOriginal !== null) return;

        $orig = $this->record->fresh();

        $this->estadoOriginal = $orig->estado_despacho;
        $this->itemsOriginales = [];

        foreach ($orig->details as $item) {
            $this->itemsOriginales[$item->id] = [
                'cantidad'     => $item->cantidad,
                'warehouse_id' => $item->warehouse_id,
                'variant_id'   => $item->variant_id,
                'unit_id'      => $item->unit_id,
            ];
        }
    }

    /**
     * BEFORE SAVE: decidir acción de stock
     */
    protected function beforeSave(): void
    {
        $this->loadOriginalSnapshot();

        $nuevoEstado = $this->data['estado_despacho'];
        $this->shouldApplyStock = false;

        /** Detectar cambios en items */
        $incoming = $this->data['details'] ?? [];

        $newMap = [];
        $newIds = [];

        foreach ($incoming as $i => $d) {
            if (isset($d['id']) && $d['id']) {
                $key = (int) $d['id'];
                $newIds[] = $key;
            } else {
                $key = 'new::' . $i;
            }

            $newMap[$key] = [
                'cantidad'     => $d['cantidad'] ?? 0,
                'warehouse_id' => $d['warehouse_id'] ?? null,
                'variant_id'   => $d['variant_id'] ?? null,
                'unit_id'      => $d['unit_id'] ?? null,
            ];
        }

        $originalIds = array_keys($this->itemsOriginales);

        $removed = array_diff($originalIds, $newIds);
        $added   = array_filter(array_keys($newMap), fn($k) => is_string($k));

        $changed = false;

        foreach ($newMap as $key => $vals) {
            if (!is_int($key)) continue;

            if (!isset($this->itemsOriginales[$key])) {
                $changed = true;
                break;
            }

            $orig = $this->itemsOriginales[$key];

            if (
                floatval($orig['cantidad']) !== floatval($vals['cantidad']) ||
                $orig['warehouse_id'] !== $vals['warehouse_id'] ||
                $orig['variant_id']   !== $vals['variant_id'] ||
                $orig['unit_id']      !== $vals['unit_id']
            ) {
                $changed = true;
                break;
            }
        }

        if (count($removed) > 0 || count($added) > 0) {
            $changed = true;
        }

        /** Reglas */
        $old = $this->estadoOriginal;
        $new = $nuevoEstado;

        /** Tipo de movimiento */
        $this->movimiento = $this->buildMovimientoLabel($changed, $old, $new);

        // 1) pendiente → pendiente
        if ($old === 'pendiente' && $new === 'pendiente') {
            return; // nada
        }

        // 2) pendiente → recibido (solo aplicar)
        if ($old === 'pendiente' && $new === 'recibido') {
            $this->shouldApplyStock = true;
            return;
        }

        // 3) recibido → pendiente (solo revertir)
        if ($old === 'recibido' && $new === 'pendiente') {
            if (!$this->alreadyReversed) {
                $this->reversePurchase($this->record, $this->movimiento);
                $this->alreadyReversed = true;
            }
            return;
        }

        // 4) recibido → recibido
        if ($old === 'recibido' && $new === 'recibido') {
            if ($changed) {
                if (!$this->alreadyReversed) {
                    $this->reversePurchase($this->record, $this->movimiento);
                    $this->alreadyReversed = true;
                }

                $this->shouldApplyStock = true;
            }
            return;
        }
    }

    /**
     * Construir etiqueta tipo de movimiento
     */
    private function buildMovimientoLabel(bool $changed, string $old, string $new): string
    {
        if ($old !== $new) {
            return "despacho: $old → $new";
        }
        if ($changed) {
            return "ajuste en item";
        }
        return "sin cambios";
    }

    /**
     * After Save: aplicar si corresponde
     */
    protected function afterSave(): void
    {
        if ($this->shouldApplyStock) {
            $this->applyPurchase($this->record, $this->movimiento);
        }
    }
}
