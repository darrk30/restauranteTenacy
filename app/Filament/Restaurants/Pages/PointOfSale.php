<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Client;
use App\Models\Floor;
use App\Models\Order;
use App\Models\Table;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\DocumentoService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;

class PointOfSale extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Punto de venta';
    // protected static ?string $navigationGroup = 'Punto de venta';
    protected static ?string $title = 'Punto de venta';
    protected static string $view = 'filament.pdv.point-of-sale';
    protected static ?int $navigationSort = 1;

    public $mostrarModalComanda = false;
    public ?Order $ordenGenerada = null;

    public $tipoDoc = 'DNI';
    public $numDoc = '';
    public $nombresCliente = '';
    public $apellidosCliente = '';
    public $direccionCliente = '';
    public $telefonoCliente = '';
    public $repartidorId = '';
    public $mostrarModalDetalles = false;
    public ?Order $ordenParaDetalles = null;
    public $mostrarModalCambioMesa = false;
    public $mesaOrigenId = null;
    public $mesaDestinoId = null;
        // Añade esta variable pública a tu componente
    public $repartidorAsignadoRapido = '';

    public function mount()
    {
        if (session()->has('print_order_id')) {
            $idOrden = session('print_order_id');
            $this->ordenGenerada = Order::with(['details.product.production.printer', 'table', 'user'])->find($idOrden);
            if ($this->ordenGenerada) {
                $this->mostrarModalComanda = true;
            }
        }
    }

    public static function canAccess(): bool
    {
        if (! Filament::getTenant()) {
            return false;
        }

        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return false;
        }

        try {
            return $user->hasPermissionTo('ver_punto_venta_rest');
        } catch (\Exception $e) {
            return false;
        }
    }


    public function avanzarEstado($orderId)
    {
        $order = Order::find($orderId);
        if (!$order) return;

        $estadoActual = $order->status_llevar_delivery;
        $canal = $order->canal;

        // Lógica de transición de estados
        if ($canal === 'llevar') {
            if ($estadoActual === 'preparando') {
                $order->update(['status_llevar_delivery' => 'entregado']);
                Notification::make()->title('Pedido entregado y finalizado')->success()->send();
            }
        } elseif ($canal === 'delivery') {
            if ($estadoActual === 'preparando') {
                $order->update(['status_llevar_delivery' => 'enviado']);
                Notification::make()->title('Pedido enviado con repartidor')->info()->send();
            } elseif ($estadoActual === 'enviado') {
                $order->update(['status_llevar_delivery' => 'entregado']);
                Notification::make()->title('Delivery finalizado con éxito')->success()->send();
            }
        }
    }

    public function getTableStats(): array
    {
        $tables = Table::where('restaurant_id', Filament::getTenant()->id)->get();

        return [
            'libres'   => $tables->where('estado_mesa', 'libre')->count(), // Ajusta el string según tu BD
            'ocupadas' => $tables->where('estado_mesa', 'ocupada')->count(),
            'pagando'  => $tables->where('estado_mesa', 'pagando')->count(),
        ];
    }

    public function getChannelCounts(): array
    {
        $tenantId = Filament::getTenant()->id;

        // 1. Conteo de Salón (Mesas ocupadas o pagando)
        $salonCount = Table::where('restaurant_id', $tenantId)
            ->whereIn('estado_mesa', ['ocupada', 'pagando'])
            ->count();

        // Usamos la misma lógica de filtrado que tienes en getViewData para ser consistentes
        $baseQuery = Order::where('restaurant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('status_llevar_delivery', '!=', 'entregado')
                    ->orWhere('status', '!=', 'pagado')
                    ->orWhereNull('status_llevar_delivery');
            });

        return [
            'salon' => $salonCount,
            'llevar' => (clone $baseQuery)->where('canal', 'llevar')->count(),
            'delivery' => (clone $baseQuery)->where('canal', 'delivery')->count(),
        ];
    }

    // 2. MÉTODO PARA VER DETALLES (Ojo)
    public function verDetalles($orderId)
    {
        $this->ordenParaDetalles = Order::with('details')->find($orderId);
        $this->mostrarModalDetalles = true;
    }

    // Método optimizado para ser llamado desde el modal
public function cargarDetallesOrden($id)
    {
        $this->ordenParaDetalles = \App\Models\Order::with('details')->find($id);

        // 🟢 INICIALIZAMOS EL SELECT CON EL REPARTIDOR ACTUAL (Si existe)
        if ($this->ordenParaDetalles && $this->ordenParaDetalles->canal === 'delivery') {
            $this->repartidorAsignadoRapido = $this->ordenParaDetalles->delivery_id ?? '';
        }
    }

    // Método para limpiar al cerrar (opcional, para ahorrar memoria)
    public function limpiarDetalles()
    {
        $this->ordenParaDetalles = null;
    }

    public function consultarDocumento()
    {
        $this->resetErrorBag();
        if (empty($this->numDoc)) {
            $this->addError('numDoc', 'Ingrese un número');
            return;
        }

        $clienteExistente = Client::where('numero', $this->numDoc)
            ->where('restaurant_id', Filament::getTenant()->id)
            ->first();

        if ($clienteExistente) {
            $this->nombresCliente = $clienteExistente->nombres ?? $clienteExistente->razon_social;
            $this->apellidosCliente = $clienteExistente->apellidos;
            $this->direccionCliente = $clienteExistente->direccion;
            $this->telefonoCliente = $clienteExistente->telefono;
            $docType = TypeDocument::find($clienteExistente->type_document_id);
            $this->tipoDoc = $docType ? $docType->code : 'DNI';
            return;
        }

        if ($this->tipoDoc === 'RUC') {
            $data = DocumentoService::consultarRuc($this->numDoc);
            if ($data && isset($data['razonSocial'])) {
                $this->nombresCliente = $data['razonSocial'];
                $this->apellidosCliente = '';
                $this->direccionCliente = $data['direccion'] ?? '';
            } else {
                $this->addError('numDoc', 'RUC no encontrado');
            }
        } else {
            $data = DocumentoService::consultarDni($this->numDoc);
            if ($data && isset($data['nombres'])) {
                $this->nombresCliente = $data['nombres'];
                $this->apellidosCliente = trim(($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? ''));
            } else {
                $this->addError('numDoc', 'DNI no encontrado');
            }
        }
    }

    public function prepararClienteYRedirigir($canal)
    {
        $rules = ['nombresCliente' => 'required|min:3'];

        // Si hay documento o es Delivery, apellidos son obligatorios (excepto RUC)
        if ((!empty($this->numDoc)) && $this->tipoDoc === 'DNI') {
            $rules['apellidosCliente'] = 'required|min:2';
        }

        if ($canal === 'delivery') {
            $rules['direccionCliente'] = 'required';
            $rules['telefonoCliente'] = 'required';
            $rules['repartidorId'] = 'required';
        }

        $this->validate($rules, [
            'nombresCliente.required' => 'El nombre es obligatorio',
            'repartidorId.required' => 'Seleccione un repartidor',
            'direccionCliente.required' => 'La dirección es obligatoria',
        ]);

        $customerId = null;
        $nombreRepartidor = null;

        if ($canal === 'delivery') {
            $repartidor = User::find($this->repartidorId);
            $nombreRepartidor = $repartidor?->name;
        }

        if (!empty($this->numDoc)) {
            $typeDocument = TypeDocument::where('code', $this->tipoDoc)
                ->where('restaurant_id', Filament::getTenant()->id)->first();

            $cliente = Client::updateOrCreate(
                ['numero' => $this->numDoc, 'restaurant_id' => Filament::getTenant()->id],
                [
                    'nombres' => ($this->tipoDoc === 'DNI') ? $this->nombresCliente : null,
                    'apellidos' => ($this->tipoDoc === 'DNI') ? $this->apellidosCliente : null,
                    'razon_social' => ($this->tipoDoc === 'RUC') ? $this->nombresCliente : null,
                    'type_document_id' => $typeDocument?->id,
                    'direccion' => $this->direccionCliente,
                    'telefono' => $this->telefonoCliente,
                ]
            );
            $customerId = $cliente->id;
        }

        $nombreCompleto = ($this->tipoDoc === 'RUC' || empty($this->apellidosCliente))
            ? $this->nombresCliente
            : trim($this->nombresCliente . ' ' . $this->apellidosCliente);

        $params = [
            'canal' => $canal,
            'nombre' => $nombreCompleto,
            'direccion' => $this->direccionCliente,
            'telefono' => $this->telefonoCliente,
            'delivery_id' => $this->repartidorId,
            'nombre_delivery' => $nombreRepartidor,
            'cliente_id' => $customerId,
        ];

        return redirect()->to("/app/orden-mesa/nuevo?" . http_build_query($params));
    }

    public function resetForm()
    {
        $this->reset(['tipoDoc', 'numDoc', 'nombresCliente', 'apellidosCliente', 'direccionCliente', 'telefonoCliente', 'repartidorId']);
        $this->resetErrorBag();
    }

    public function cerrarModalComanda()
    {
        $this->mostrarModalComanda = false;
        $this->ordenGenerada = null;

        // Limpiamos las sesiones de impresión para que no vuelva a salir al recargar
        session()->forget('print_job_id');
        session()->forget('print_order_id');
    }

    // 3. CONSULTA FILTRADA (getViewData)
    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        // Filtro común: status de pago no cancelado Y status logístico NO entregado
        $baseQuery = Order::where('restaurant_id', $tenant->id)
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('status_llevar_delivery', '!=', 'entregado') // Aún no se entrega
                    ->orWhere('status', '!=', 'pagado')                  // O aún no se paga
                    ->orWhereNull('status_llevar_delivery');             // O es nuevo
            });

        return [
            'floors' => Floor::with('tables')->get(),
            'tenant' => $tenant,
            'repartidores' => $tenant->users()->get(['users.id', 'users.name']),

            // Filtramos por canal sobre la query base
            'ordersLlevar' => (clone $baseQuery)->where('canal', 'llevar')->latest()->get(),
            'ordersDelivery' => (clone $baseQuery)->where('canal', 'delivery')->latest()->get(),
        ];
    }

    public function cerrarModalDetalles()
    {
        $this->mostrarModalDetalles = false;
        $this->ordenParaDetalles = null;
    }

    public function iniciarAtencion($mesaId, $personas)
    {
        session()->flash('personas_iniciales', $personas);
        return redirect()->to("/app/orden-mesa/{$mesaId}");
    }

    public function abrirModalCambioMesa($mesaOrigenId)
    {
        $this->mesaOrigenId = $mesaOrigenId;
        $this->mesaDestinoId = null; // Resetear la selección anterior
        $this->mostrarModalCambioMesa = true;
    }

    public function cerrarModalCambioMesa()
    {
        $this->mostrarModalCambioMesa = false;
        $this->mesaOrigenId = null;
        $this->mesaDestinoId = null;
    }

    public function cambiarMesa()
    {
        // 1. Validaciones
        if (!$this->mesaOrigenId || !$this->mesaDestinoId) {
            Notification::make()->title('Debe seleccionar una mesa de destino')->warning()->send();
            return;
        }

        if ($this->mesaOrigenId == $this->mesaDestinoId) {
            Notification::make()->title('La mesa de destino debe ser diferente a la actual')->warning()->send();
            return;
        }

        // 2. Obtener las mesas
        $mesaOrigen = Table::find($this->mesaOrigenId);
        $mesaDestino = Table::find($this->mesaDestinoId);

        if (!$mesaOrigen || !$mesaOrigen->order_id) {
            Notification::make()->title('La mesa de origen no tiene una orden activa')->danger()->send();
            $this->cerrarModalCambioMesa();
            return;
        }

        if (!$mesaDestino || strtolower($mesaDestino->estado_mesa) !== 'libre') {
            Notification::make()->title('La mesa de destino no está libre')->danger()->send();
            return;
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $orderId = $mesaOrigen->order_id;
            $asientos = $mesaOrigen->asientos;

            // 3. Actualizar la orden con la nueva mesa
            Order::where('id', $orderId)->update(['table_id' => $mesaDestino->id]);

            // 4. Actualizar la mesa de destino (Ocuparla)
            $mesaDestino->update([
                'estado_mesa' => 'ocupada',
                'order_id' => $orderId,
                'asientos' => $asientos
            ]);

            // 5. Liberar la mesa de origen
            $mesaOrigen->update([
                'estado_mesa' => 'libre',
                'order_id' => null,
                'asientos' => 0
            ]);

            \Illuminate\Support\Facades\DB::commit();

            Notification::make()
                ->title('Orden movida con éxito')
                ->body("La orden se trasladó a la mesa {$mesaDestino->name}.")
                ->success()
                ->send();

            $this->cerrarModalCambioMesa();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            Notification::make()->title('Error al cambiar de mesa')->body($e->getMessage())->danger()->send();
        }
    }

    public function asignarRepartidorRapido($orderId)
    {
        $order = \App\Models\Order::find($orderId);
        
        // Si se selecciona un repartidor, lo asignamos. Si se selecciona la opción vacía, lo removemos.
        $repartidorId = $this->repartidorAsignadoRapido ?: null;
        $repartidorNombre = null;

        if ($repartidorId) {
            $repartidor = \App\Models\User::find($repartidorId);
            $repartidorNombre = $repartidor ? $repartidor->name : null;
        }

        if ($order) {
            $order->update([
                'delivery_id' => $repartidorId,
                'nombre_delivery' => $repartidorNombre,
            ]);

            \Filament\Notifications\Notification::make()
                ->title($repartidorId ? 'Repartidor asignado/cambiado' : 'Repartidor removido')
                ->success()
                ->send();
                
            $this->cargarDetallesOrden($orderId); // Recargamos el modal
        }
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
