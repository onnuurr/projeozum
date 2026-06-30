<?php

namespace Modules\Tenant\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Hepsiburada webhook iskelet — live aktive olmadan 503 döner.
 * Doc inceleme sonrası HMAC + merchantId resolver + job dispatch eklenecek.
 */
class HepsiburadaWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return response('Hepsiburada webhook henüz aktif değil.', 503);
    }
}
