<?php

namespace Modules\Tenant\Services\Marketplace\Trendyol;

use Illuminate\Http\Request;
use Modules\Tenant\Models\TenantMarketplaceCredential;

/**
 * Trendyol webhook imza doğrulama.
 *
 * Trendyol "X-Trendyol-Signature" header'ı ile body'nin HMAC-SHA256'sını gönderir
 * (api_secret ile imzalanmış). Constant-time compare ile doğrula.
 *
 * NOT: Gerçek Trendyol webhook imza şeması partner programıyla doğrulanmalı —
 * bu uygulama Phase 3 dokümantasyon alt-task'ında final hale getirilecek.
 */
class TrendyolWebhookVerifier
{
    public function verify(Request $request, TenantMarketplaceCredential $credential): bool
    {
        $signature = $request->header('X-Trendyol-Signature') ?? '';
        if ($signature === '') {
            return false;
        }

        $secret = (string) $credential->api_secret;
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
