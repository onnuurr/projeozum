<?php

namespace Modules\Tenant\Services\Marketplace\Contracts;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

/**
 * Her marketplace adapter'ı bu interface'i implement eder.
 *
 * Provider başına ayrı service ağacı kuralı: her implementation kendi auth
 * şemasını, payload mapper'ını, webhook verifier'ını içerir. Generic factory yok —
 * MarketplaceServiceResolver match expression ile doğru sınıfı döner.
 */
interface MarketplaceClient
{
    /**
     * Provider'ın kısa kodu (resolver match key'i): 'trendyol' | 'hepsiburada' | 'n11' | 'ciceksepeti'.
     */
    public function code(): string;

    /**
     * Ürünü provider'a push et. Listing kaydı varsa update, yoksa create.
     */
    public function pushProduct(Product $product): PushResult;

    /**
     * Verilen tarihten beri açılmış siparişleri çek. Provider'a özel ham veri DTO'ya map'lenir.
     *
     * @return Collection<int, \Modules\Tenant\Services\Marketplace\DTOs\MarketplaceOrderDTO>
     */
    public function fetchOrders(DateTimeInterface $since): Collection;

    /**
     * Verilen tarih aralığındaki finansal raporu çek (komisyon, kargo, iade vb.).
     */
    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult;

    /**
     * Webhook payload'unun provider'dan geldiğini HMAC ile doğrular.
     */
    public function verifyWebhook(Request $request): bool;
}
