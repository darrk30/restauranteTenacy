<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use App\Services\OrdenService;
use App\Enums\TipoProducto;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CartaController extends Controller
{
    public function index(Restaurant $tenant)
    {
        // 1. Validaciones de estado
        if ($tenant->carta_activa_admin !== 'activo') {
            return view('catalogo.carta_404', compact('tenant'));
        }

        $config = Configuration::where('restaurant_id', $tenant->id)->first();
        $mesaId = request()->query('mesa');
        // Extraemos los booleanos (si no hay config, por defecto los ponemos en false o true según tu lógica)
        $guardarPedidosWeb = $config ? (bool) $config->guardar_pedidos_web : false;
        $ofreceDelivery    = $config ? (bool) $config->habilitar_delivery_web : false;
        $ofreceRecojo      = $config ? (bool) $config->habilitar_recojo_web : false;
        $metodosPago = PaymentMethod::where('restaurant_id', $tenant->id)
            ->where('status', true)
            ->pluck('name', 'id')
            ->toArray();

        $promociones = Banner::where('restaurant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($b) => [
                'type' => $b->type,
                'bg_color' => $b->bg_color,
                'title' => $b->title,
                'image' => asset('storage/' . $b->image),
                'image_mobile' => $b->image_mobile ? asset('storage/' . $b->image_mobile) : null,
                'link' => $b->link,
            ]);

        $categorias = OrdenService::obtenerCategorias();
        $itemsCrudos = OrdenService::obtenerProductos();

        $productos = $itemsCrudos->map(function ($item) {
            $isPromo = $item->type === TipoProducto::Promocion->value;

            // 🟢 Corrección del Error: Validación de nulidad antes de usar pluck()
            if ($isPromo) {
                $catString = $item->category ? strtolower($item->category->name) : 'promociones';
            } else {
                // Verificamos si existe la relación y no está vacía
                $catString = ($item->categories && $item->categories->count() > 0)
                    ? $item->categories->pluck('name')->map(fn($c) => strtolower($c))->implode(',')
                    : 'general';
            }

            return [
                'id' => $isPromo ? 'promo_' . $item->id : $item->id,
                'name' => $item->name,
                'categories' => $catString,
                'price' => (float) $item->price,
                'description' => Str::limit($item->description, 40) ?? '',
                'long_description' => $item->description,
                'badge' => $isPromo ? 'Promo' : ($item->badge ?? null),
                'image' => $item->image_path ? asset('storage/' . $item->image_path) : null,
                'gallery' => !empty($item->gallery)
                    ? collect($item->gallery)->map(fn($g) => asset('storage/' . $g))->toArray()
                    : ($item->image_path ? [asset('storage/' . $item->image_path)] : []),
                'attributes' => $this->mapAttributes($item, $isPromo),
                'variants' => $this->mapVariants($item, $isPromo),
            ];
        })->values();

        return view('catalogo.carta-digital', compact('tenant', 'mesaId', 'promociones', 'metodosPago', 'categorias', 'productos', 'ofreceDelivery', 'ofreceRecojo', 'guardarPedidosWeb'));
    }

    public function procesarPedido(Restaurant $tenant, Request $request)
    {
        try {
            $totalRealServidor = 0;

            // Lógica Segura de Recálculo
            $carritoService = collect($request->items)->map(function ($item) use (&$totalRealServidor) {
                $isPromo = str_starts_with($item['id'], 'promo_');
                $realId = str_replace('promo_', '', $item['id']);

                $precioItemBD = 0;
                $nombreBD = "";

                if ($isPromo) {
                    $promo = Promotion::findOrFail($realId);
                    $precioItemBD = $promo->price;
                    $nombreBD = $promo->name;
                } else {
                    $prod = Product::findOrFail($realId);
                    $precioItemBD = $prod->price;
                    $nombreBD = $prod->name;

                    if (!empty($item['variant_id'])) {
                        $variante = \App\Models\Variant::with('values')->find($item['variant_id']);
                        if ($variante) {
                            $precioItemBD += collect($variante->values)->sum('extra');
                            $nombreBD .= " (" . $variante->values->pluck('name')->implode('/') . ")";
                        }
                    }
                }

                $cantidad = abs((int) $item['qty']);
                $subtotal = $precioItemBD * $cantidad;
                $totalRealServidor += $subtotal;

                return [
                    'product_id'   => $isPromo ? null : $realId,
                    'promotion_id' => $isPromo ? $realId : null,
                    'type'         => $isPromo ? TipoProducto::Promocion->value : TipoProducto::Producto->value,
                    'name'         => $nombreBD,
                    'price'        => $precioItemBD,
                    'quantity'     => $cantidad,
                    'total'        => $subtotal,
                    'notes'        => strip_tags($item['notes'] ?? ''),
                    'variant_id'   => $item['variant_id'] ?? null,
                ];
            })->toArray();

            $divisor = get_tax_divisor($tenant->id);
            $subtotalCalculado = $totalRealServidor / $divisor;

            $datosOrden = [
                'restaurant_id'     => $tenant->id,
                'canal'             => $request->mesa_id ? 'salon' : $request->tipo_pedido,
                'mesa_id'           => $request->mesa_id,
                'nombre_cliente'    => strip_tags($request->cliente_nombre ?? 'Cliente Digital'),
                'direccion'         => strip_tags($request->cliente_direccion),
                'telefono'          => strip_tags($request->cliente_telefono),
                'subtotal'          => $subtotalCalculado,
                'igv'               => $totalRealServidor - $subtotalCalculado,
                'total'             => $totalRealServidor,
                'web'               => true,
                'payment_method_id' => $request->metodo_pago,
                'notas'             => strip_tags($request->notas),
            ];

            $admin = User::where('restaurant_id', $tenant->id)->first() ?? User::first();
            $resultado = OrdenService::crearPedido($datosOrden, $carritoService, $admin->id);

            return response()->json([
                'success' => true,
                'whatsapp_url' => $this->generarLinkWhatsApp($tenant, $resultado['order'], $carritoService)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function generarLinkWhatsApp($tenant, $order, $items)
    {
        $msg = "🍕 *NUEVO PEDIDO DIGITAL #{$order->code}*\n\n";

        if ($order->table_id) {
            $order->load('table');
            $msg .= "📍 *Mesa:* " . ($order->table->name ?? '---') . "\n";
        } else {
            $msg .= "👤 *Cliente:* {$order->nombre_cliente}\n📦 *Tipo:* " . strtoupper($order->canal) . "\n";
        }

        if ($order->canal === 'delivery') $msg .= "🛵 *Dirección:* {$order->direccion}\n";

        $msg .= "\n📝 *DETALLE:*\n";
        foreach ($items as $i) $msg .= "• {$i['quantity']}x {$i['name']} (S/ " . number_format($i['price'], 2) . ")\n";

        $msg .= "\n💰 *TOTAL: S/ " . number_format($order->total, 2) . "*\n";
        if ($order->notas) $msg .= "\n⚠️ *Notas:* {$order->notas}";

        return "https://api.whatsapp.com/send?phone=" . $tenant->phone . "&text=" . urlencode($msg);
    }

    private function mapAttributes($item, $isPromo)
    {
        if ($isPromo || !$item->attributes) return [];

        return $item->attributes->map(function ($attribute) {
            $valores = is_string($attribute->pivot->values)
                ? json_decode($attribute->pivot->values, true)
                : ($attribute->pivot->values ?? []);

            return [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'options' => collect($valores)->map(fn($opcion) => [
                    'id' => $opcion['id'] ?? uniqid(),
                    'name' => $opcion['name'] ?? 'Opción',
                    'price' => (float) ($opcion['extra'] ?? 0),
                ])->values()->toArray(),
            ];
        })->toArray();
    }

    private function mapVariants($item, $isPromo)
    {
        if ($isPromo || !$item->variants) return [];

        return $item->variants->map(fn($v) => [
            'id' => $v->id,
            'name' => $v->full_name,
            'image' => $v->image_path ? asset('storage/' . $v->image_path) : null,
            'stock' => $v->stock->stock_real ?? null,
        ])->toArray();
    }

    public function procesarPedidoSoloWsp(Restaurant $tenant, Request $request)
    {
        try {
            $totalRealServidor = 0;

            // 1. Recalcular precios desde la base de datos de forma segura
            $carritoService = collect($request->items)->map(function ($item) use (&$totalRealServidor) {
                $isPromo = str_starts_with($item['id'], 'promo_');
                $realId = str_replace('promo_', '', $item['id']);
                $precioItemBD = 0;
                $nombreBD = "";

                if ($isPromo) {
                    $promo = Promotion::findOrFail($realId);
                    $precioItemBD = $promo->price;
                    $nombreBD = $promo->name;
                } else {
                    $prod = Product::findOrFail($realId);
                    $precioItemBD = $prod->price;
                    $nombreBD = $prod->name;

                    if (!empty($item['variant_id'])) {
                        $variante = \App\Models\Variant::with('values')->find($item['variant_id']);
                        if ($variante) {
                            $precioItemBD += collect($variante->values)->sum('extra');
                            $nombreBD .= " (" . $variante->values->pluck('name')->implode('/') . ")";
                        }
                    }
                }

                $cantidad = abs((int) $item['qty']);
                $subtotal = $precioItemBD * $cantidad;
                $totalRealServidor += $subtotal;

                return [
                    'name'     => $nombreBD,
                    'price'    => $precioItemBD,
                    'quantity' => $cantidad,
                    'total'    => $subtotal,
                ];
            })->toArray();

            // 2. Construir el mensaje de WhatsApp
            $msg = "🍕 *NUEVO PEDIDO DIGITAL*\n\n";

            if ($request->mesa_id) {
                $msg .= "📍 *Mesa:* " . strip_tags($request->mesa_id) . "\n";
            } else {
                $tipo = strtoupper($request->tipo_pedido ?? 'PARA LLEVAR');
                $nombre = strip_tags($request->cliente_nombre ?? 'Sin nombre');

                $msg .= "👤 *Cliente:* {$nombre}\n";
                if ($request->cliente_telefono) {
                    $msg .= "📞 *Teléfono:* " . strip_tags($request->cliente_telefono) . "\n";
                }
                $msg .= "📦 *Tipo:* {$tipo}\n";

                if ($request->tipo_pedido === 'delivery') {
                    $direccion = strip_tags($request->cliente_direccion ?? 'Sin dirección');
                    $msg .= "🛵 *Dirección:* {$direccion}\n";

                    if ($request->metodo_pago) {
                        $metodo = PaymentMethod::find($request->metodo_pago);
                        $nombrePago = $metodo ? $metodo->name : 'No especificado';
                        $msg .= "💳 *Pago:* {$nombrePago}\n";
                    }
                }
            }

            $msg .= "\n📝 *DETALLE:*\n";
            foreach ($carritoService as $i) {
                $msg .= "• {$i['quantity']}x {$i['name']} (S/ " . number_format($i['price'], 2) . ")\n";
            }

            $msg .= "\n💰 *TOTAL: S/ " . number_format($totalRealServidor, 2) . "*\n";

            if ($request->notas) {
                $msg .= "\n⚠️ *Notas:* " . strip_tags($request->notas);
            }

            // 3. Obtener el número del restaurante y generar el enlace
            $telefonoRestaurante = preg_replace('/[^0-9]/', '', $tenant->phone);
            $whatsappUrl = "https://api.whatsapp.com/send?phone=" . $telefonoRestaurante . "&text=" . urlencode($msg);

            return response()->json([
                'success' => true,
                'whatsapp_url' => $whatsappUrl
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
