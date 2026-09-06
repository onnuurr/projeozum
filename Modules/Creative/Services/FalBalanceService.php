<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * fal.ai hesap kredi bakiyesi (GET /v1/account/billing?expand=credits) —
 * SADECE Admin scope key ile okunabiliyor; try-on/kompozisyon üretiminde
 * kullanılan inference key'i (`creative.ai.fal.key`) bu uç noktada 403 döner,
 * bu yüzden ayrı bir `creative.ai.fal.admin_key` okunur. Admin key hiç
 * ayarlanmadıysa (varsayılan durum) hiç HTTP çağrısı yapılmadan `null` döner
 * — Vue tarafı (CreativeTryon.vue) rozeti bu durumda hiç göstermez.
 */
class FalBalanceService
{
    private const CACHE_KEY = 'creative:fal-balance';

    // Bakiye saniyeler içinde değişmez ve dış bir HTTP çağrısı gerektirir —
    // Giydirme sayfası her açıldığında/yenilendiğinde fal'e gitmesin diye 5dk
    // cache'lenir.
    private const CACHE_TTL = 300;

    /**
     * @return array{balance:?float,currency:?string,error:?string}|null
     */
    public function current(): ?array
    {
        $key = (string) config('creative.ai.fal.admin_key');
        if ($key === '') {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($key) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['Authorization' => "Key {$key}"])
                    ->get('https://api.fal.ai/v1/account/billing', ['expand' => 'credits']);

                if ($response->failed()) {
                    return ['balance' => null, 'currency' => null, 'error' => "HTTP {$response->status()}"];
                }

                return [
                    'balance'  => $response->json('credits.current_balance'),
                    'currency' => $response->json('credits.currency'),
                    'error'    => null,
                ];
            } catch (Throwable $e) {
                return ['balance' => null, 'currency' => null, 'error' => $e->getMessage()];
            }
        });
    }
}
