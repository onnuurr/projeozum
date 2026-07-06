<?php

namespace Modules\Tenant\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CiceksepetiWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return response('Çiçeksepeti webhook henüz aktif değil.', 503);
    }
}
