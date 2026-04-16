<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function github(Request $request)
    {
        $payload = $request->getContent();
        $signature = 'sha1=' . hash_hmac('sha1', $payload, 'deployresttukipu2026@');
        if ($request->header('X-Hub-Signature') !== $signature) {
            return response('Firma no válida', 403);
        }
        putenv('HOME=/home/tukipu'); 
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');
        shell_exec('dploy deploy master');
        return response('Webhook recibido', 200);
    }
}
