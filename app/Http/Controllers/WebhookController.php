<?php

namespace App\Http\Controllers;

use App\Jobs\GithubWebhook;
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
        GithubWebhook::dispatch();//job 2
        return response('Webhook recibido', 200);
    }
}
