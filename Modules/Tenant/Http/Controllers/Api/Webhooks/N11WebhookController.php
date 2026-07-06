<?php

namespace Modules\Tenant\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class N11WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return response('N11 webhook henüz aktif değil.', 503);
    }
}
