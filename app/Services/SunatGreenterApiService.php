<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class SunatGreenterApiService
{
    protected $apiUrl;

    public function __construct()
    {
        // Asegúrate de usar la variable de entorno o config correcta donde esté tu API
        $this->apiUrl = config('app.api_facturacion_url', 'http://facturacion.test');
    }

    /**
     * Envía un comprobante (Factura/Boleta) a la API centralizada.
     */
    public function sendInvoice(array $invoiceData, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/invoices/send", $invoiceData);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'     => false,
                    'http_status' => $response->status(),
                    'error_data'  => $data,
                    'message'     => $data['error'] ?? $data['message'] ?? 'Error de validación en la API.',
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con el servidor de facturación.',
                'error'   => $e->getMessage()
            ];
        }
    }

    public function sendXmlDirect($xmlBase64, $filename, $apiKey)
    {
        try {
            $response = Http::withToken($apiKey)
                ->post($this->apiUrl . '/api/invoice/send-xml', [
                    'xml_base64' => $xmlBase64,
                    'filename'   => $filename,
                ]);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'    => false,
                    'error_data' => $data,
                    'message'    => $data['error'] ?? $data['message'] ?? 'Error al comunicarse con la API de SUNAT.'
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción de conexión: ' . $e->getMessage()
            ];
        }
    }

    // ==========================================
    // 🚀 MÉTODOS PARA RESÚMENES DIARIOS
    // ==========================================

    /**
     * Paso 1: Envía el JSON con las boletas para generar el resumen.
     */
    public function sendSummary(array $payload, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/summaries/send", $payload);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'    => false,
                    // 🟢 Captura el error específico del sistema
                    'message'    => $data['error'] ?? $data['message'] ?? 'Error de conexión con la API.',
                    'error_data' => $data,
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de facturación.',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Paso 2: Consulta el estado del ticket del resumen en SUNAT.
     */
    public function checkSummaryStatus(array $payload, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/summaries/status", $payload);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'    => false,
                    // 🟢 Captura el error específico del sistema
                    'message'    => $data['error'] ?? $data['message'] ?? 'Error al consultar el ticket.',
                    'error_data' => $data,
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de facturación.',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Envía una Comunicación de Baja (Anulación de Facturas)
     */
    public function sendVoid(array $payload, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/voids/send", $payload);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'    => false,
                    'message'    => $data['error'] ?? $data['message'] ?? 'Error al enviar la anulación.',
                    'error_data' => $data,
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con la API central.',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Consulta el estado del ticket de la anulación
     */
    public function checkVoidStatus(array $payload, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/voids/status", $payload);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'    => false,
                    'message'    => $data['error'] ?? $data['message'] ?? 'Error al consultar ticket de anulación.',
                    'error_data' => $data,
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión al consultar ticket.',
                'error'   => $e->getMessage()
            ];
        }
    }

    // ==========================================
    // 🚀 MÉTODOS PARA NOTAS DE CRÉDITO / DÉBITO
    // ==========================================

    /**
     * Envía una Nota de Crédito o Débito a la API central.
     */
    public function sendNote(array $payload, string $apiToken)
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/api/notes/send", $payload);

            if ($response->failed()) {
                $data = $response->json();
                return [
                    'success'     => false,
                    'http_status' => $response->status(),
                    'error_data'  => $data,
                    'message'     => $data['error'] ?? $data['message'] ?? 'Error al comunicarse con la API para enviar la nota.',
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción de conexión al enviar la nota.',
                'error'   => $e->getMessage()
            ];
        }
    }
}
