<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;

class SunatGreenterApiService
{
    protected $apiUrl;
    protected $apiToken;
    protected $restaurant;

    /**
     * El constructor ahora captura URL y Token automáticamente del contexto.
     */
    public function __construct($restaurant = null)
    {
        // 1. Contexto: Parámetro > Tenant de Filament
        $this->restaurant = $restaurant ?? Filament::getTenant();

        if ($this->restaurant) {
            // 2. Usar tu caché inteligente (cached_config)
            $config = $this->restaurant->cached_config;

            // Limpiamos la URL para evitar errores de doble slash
            $this->apiUrl = rtrim($config->api_url ?? env('GREENTER_API_URL', 'http://facturacion.test'), '/');
            $this->apiToken = $config->api_token;
        } else {
            // Fallback si se usa fuera de un restaurante (muy raro en tu arquitectura)
            $this->apiUrl = rtrim(env('GREENTER_API_URL', 'http://facturacion.test'), '/');
            $this->apiToken = null;
        }
    }

    /**
     * Validador interno para asegurar que tenemos credenciales antes de disparar.
     */
    protected function ensureConfigIsReady(): bool
    {
        if (empty($this->apiUrl) || empty($this->apiToken)) {
            Log::error("SunatGreenterApiService: Falta URL o Token para el restaurante ID: " . ($this->restaurant->id ?? 'N/A'));
            return false;
        }
        return true;
    }

    /**
     * Procesa la respuesta de la API de forma estandarizada.
     */
    protected function handleResponse($response)
    {
        // 1. Intentamos convertir la respuesta a un arreglo de PHP
        $data = $response->json();

        // 2. Si la respuesta no es JSON o llegó vacía
        if (is_null($data)) {
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'error_data'  => $response->body(),
                'message'     => 'La API devolvió una respuesta no válida o no es JSON.',
            ];
        }

        // 3. Verificamos fallos de red o de servidor (Errores HTTP 400, 500, etc.)
        if ($response->failed()) {
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'error_data'  => $data,
                'message'     => $this->extractErrorMessage($data),
            ];
        }

        // 4. Verificamos Falsos Positivos: HTTP 200, pero SUNAT dice "success: false"
        // Este es el caso exacto de tu captura de pantalla
        if (isset($data['sunatResponse']) && isset($data['sunatResponse']['success']) && $data['sunatResponse']['success'] === false) {
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'error_data'  => $data,
                'message'     => $this->extractErrorMessage($data),
            ];
        }

        // Verificación de Falso Positivo genérico
        if (isset($data['success']) && $data['success'] === false) {
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'error_data'  => $data,
                'message'     => $this->extractErrorMessage($data),
            ];
        }

        // 5. Todo salió perfecto
        return [
            'success' => true,
            'data'    => $data,
        ];
    }

    /**
     * Función auxiliar inteligente para extraer el mensaje de error de cualquier estructura.
     */
    protected function extractErrorMessage(array $data): string
    {
        // Caso 1: La estructura exacta que me mostraste (sunatResponse -> error -> message)
        if (isset($data['sunatResponse']['error']['message'])) {
            $code = $data['sunatResponse']['error']['code'] ?? 'API';
            return "SUNAT [$code]: " . $data['sunatResponse']['error']['message'];
        }

        // Caso 2: Si el error viene en la raíz como un array con 'message'
        if (isset($data['error']['message'])) {
            return $data['error']['message'];
        }

        // Caso 3: Si viene como un mensaje directo en la raíz
        if (isset($data['message'])) {
            return is_string($data['message']) ? $data['message'] : json_encode($data['message']);
        }

        // Caso 4: Si el error viene como string directo
        if (isset($data['error']) && is_string($data['error'])) {
            return $data['error'];
        }

        // Default si la API mandó un JSON raro que no sabemos leer
        return 'Error en la API de Facturación, pero no se proporcionó un mensaje detallado.';
    }

    // ==========================================
    // 🚀 ENVÍO DE COMPROBANTES (DIRECTOS)
    // ==========================================

    public function sendInvoice(array $invoiceData)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/invoices/send", $invoiceData);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    public function sendXmlDirect($xmlBase64, $filename)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/invoice/send-xml", [
                    'xml_base64' => $xmlBase64,
                    'filename'   => $filename,
                ]);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Excepción de conexión XML: ' . $e->getMessage()];
        }
    }

    // ==========================================
    // 🚀 RESÚMENES DIARIOS (BOLETAS)
    // ==========================================

    public function sendSummary(array $payload)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/summaries/send", $payload);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al enviar resumen: ' . $e->getMessage()];
        }
    }

    public function checkSummaryStatus(array $payload)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/summaries/status", $payload);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al consultar ticket: ' . $e->getMessage()];
        }
    }

    // ==========================================
    // 🚀 ANULACIONES (FACTURAS)
    // ==========================================

    public function sendVoid(array $payload)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/voids/send", $payload);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error de conexión en anulación.'];
        }
    }

    public function checkVoidStatus(array $payload)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/voids/status", $payload);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error de conexión en ticket de anulación.'];
        }
    }

    // ==========================================
    // 🚀 NOTAS DE CRÉDITO / DÉBITO
    // ==========================================

    public function sendNote(array $payload)
    {
        if (!$this->ensureConfigIsReady()) return ['success' => false, 'message' => 'Configuración API incompleta.'];

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/api/notes/send", $payload);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Excepción al enviar nota.'];
        }
    }
}
