<?php

namespace App\Http\Controllers;

use App\Jobs\GithubWebhook;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    // public function github(Request $request)
    // {
    //     $payload = $request->getContent();
    //     $signature = 'sha1=' . hash_hmac('sha1', $payload, 'deployresttukipu2026@');
    //     if ($request->header('X-Hub-Signature') !== $signature) {
    //         return response('Firma no válida', 403);
    //     }
    //     GithubWebhook::dispatch();//job 3
    //     return response('Webhook recibido', 200);
    // }

    public function github(Request $request)
    {
        $payloadRaw = $request->getContent();
        $data = json_decode($payloadRaw, true);

        // 1. Validar Firma (Seguridad)
        $secret = 'deployresttukipu2026@';
        $signature = 'sha1=' . hash_hmac('sha1', $payloadRaw, $secret);

        if ($request->header('X-Hub-Signature') !== $signature) {
            return response('Firma no válida', 403);
        }
        // 2. FILTRO DE EVENTO Y RAMA
        // Verificamos que sea un evento de Pull Request
        if (isset($data['pull_request'])) {
            $action = $data['action']; // opened, closed, synchronized, etc.
            $isMerged = $data['pull_request']['merged']; // true si se fusionó
            $targetBranch = $data['pull_request']['base']['ref']; // rama destino (ej: master)

            // SOLO disparamos si el PR se CERRÓ, se FUSIONÓ y el destino es MASTER
            if ($action === 'closed' && $isMerged && $targetBranch === 'master') {
                GithubWebhook::dispatch();
                return response('Despliegue enviado a la cola (PR merged to master)', 200);
            }

            return response('Evento de PR recibido pero ignorado (no cumple condiciones)', 200);
        }

        return response('Evento no reconocido', 200);
    }
}
