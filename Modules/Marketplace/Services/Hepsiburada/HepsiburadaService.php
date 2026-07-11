<?php

namespace Modules\Marketplace\Services\Hepsiburada;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Marketplace\Services\AbstractMarketplaceService;
use Modules\Marketplace\Services\DTOs\PushResult;
use Modules\Marketplace\Services\DTOs\ReportResult;

/**
 * Hepsiburada MPOP live adapter — iskelet.
 *
 * Auth: Basic (merchantId:apiKey). Bkz. https://developers.hepsiburada.com/
 * Phase 3 alt-task'ı dokümantasyon araştırması ile finalize edecek.
 */
class HepsiburadaService extends AbstractMarketplaceService
{
    public function code(): string
    {
        return 'hepsiburada';
    }

    public function pushProduct(Product $product): PushResult
    {
        throw NotSupportedException::liveDriverMissing('Hepsiburada', 'pushProduct');
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        throw NotSupportedException::liveDriverMissing('Hepsiburada', 'fetchOrders');
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        throw NotSupportedException::liveDriverMissing('Hepsiburada', 'fetchReports');
    }

    public function verifyWebhook(Request $request): bool
    {
        // Hepsiburada webhook'u şu an aktif değil; provider doc inceleneceğinde
        // HMAC veya Basic-Auth replay verifier eklenecek.
        return false;
    }
}
