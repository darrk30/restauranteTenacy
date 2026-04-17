<?php

namespace App\Http\Controllers;

use App\Jobs\GithubWebhook;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function github(Request $request)
    {
        $payloadRaw = $request->getContent();
        
        // 1. Validar la firma (Usando config para que no falle en producción)
        $secret = config('services.github.webhook_secret', 'deployresttukipu2026@');
        $signature = 'sha1=' . hash_hmac('sha1', $payloadRaw, $secret);

        if ($request->header('X-Hub-Signature') !== $signature) {
            return response('Firma no válida', 403);
        }

        // 2. Convertir el contenido en array para leer los datos
        $data = json_decode($payloadRaw, true);

        // Verificamos que sea un evento de pull_request
        if (isset($data['pull_request'])) {
            
            $action = $data['action']; // opened, closed, synchronized, etc.
            $targetBranch = $data['pull_request']['base']['ref']; // La rama destino
            $isMerged = $data['pull_request']['merged']; // Si ya se fusionó

            // SOLO disparamos si el PR se cerró Y se fusionó exitosamente a MASTER
            if ($action === 'closed' && $targetBranch === 'master' && $isMerged) {
                GithubWebhook::dispatch();
                return response('Despliegue enviado a la cola (PR merged to master)', 200);
            }
            
            return response('PR recibido pero no cumple condiciones de despliegue', 200);
        }

        return response('Evento ignorado', 200);
    }
}