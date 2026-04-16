<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function github(Request $request)
    {
       putenv('HOME=/home/tukipu');
       putenv('PATH=/usr/local/bin:/usr/bin:/bin');
       shell_exec('dploy deploy master');
       return response('Webhook recibido', 200);
    }
}
