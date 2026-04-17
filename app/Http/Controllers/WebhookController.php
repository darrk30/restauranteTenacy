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
    //     GithubWebhook::dispatch();//job
    //     return response('Webhook recibido', 200);
    // }
    public function github(Request $request)
    {
        $payload = $request->getContent();
        $secret = 'deployresttukipu2026@';
        $signature = 'sha1=' . hash_hmac('sha1', $payload, $secret);

        if ($request->header('X-Hub-Signature') !== $signature) {
            \Log::error('Firma de GitHub no coincide', [
                'recibida' => $request->header('X-Hub-Signature'),
                'calculada' => $signature
            ]);
            return response('Firma no válida', 403);
        }

        \Log::info('Webhook recibido correctamente, despachando Job...');
        GithubWebhook::dispatch();
        
        return response('Webhook recibido', 200);
    }
}
