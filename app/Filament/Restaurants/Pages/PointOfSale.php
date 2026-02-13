<?php

namespace App\Filament\Restaurants\Pages;

use App\Models\Client;
use App\Models\Floor;
use App\Models\Order;
use App\Models\TypeDocument;
use App\Models\User;
use App\Services\DocumentoService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;

class PointOfSale extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationLabel = 'Punto de venta';
    protected static ?string $navigationGroup = 'Punto de venta';
    protected static ?string $title = 'Punto de venta';
    protected static string $view = 'filament.pdv.point-of-sale';

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

    // 2. MÉTODO PARA VER DETALLES (Ojo)
    public function verDetalles($orderId)
    {
        $this->ordenParaDetalles = Order::with('details')->find($orderId);
        $this->mostrarModalDetalles = true;
    }

    // Método optimizado para ser llamado desde el modal
public function cargarDetallesOrden($orderId)
{
    // Simplemente cargamos la orden, la vista se actualizará reactivamente
    $this->ordenParaDetalles = Order::with(['details', 'user'])->find($orderId);
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
        if (($canal === 'delivery' || !empty($this->numDoc)) && $this->tipoDoc === 'DNI') {
            $rules['apellidosCliente'] = 'required|min:2';
        }

        if ($canal === 'delivery') {
            $rules['direccionCliente'] = 'required';
            $rules['telefonoCliente'] = 'required';
            $rules['repartidorId'] = 'required';
        }

        $this->validate($rules, [
            'nombresCliente.required' => 'El nombre es obligatorio',
            'apellidosCliente.required' => 'Los apellidos son obligatorios',
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

    // 3. CONSULTA FILTRADA (getViewData)
    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        // Filtro común: status de pago no cancelado Y status logístico NO entregado
        $baseQuery = Order::where('restaurant_id', $tenant->id)
            ->where('status', '!=', 'cancelado') 
            ->where(function($q) {
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

    public function getHeading(): string
    {
        return '';
    }
    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }
}
