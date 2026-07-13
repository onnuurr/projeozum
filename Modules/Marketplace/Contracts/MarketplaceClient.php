<?php

namespace Modules\Marketplace\Contracts;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;

/**
 * Her marketplace adapter'ı bu interface'i implement eder.
 *
 * Provider başına ayrı service ağacı kuralı: her implementation kendi auth
 * şemasını, payload mapper'ını, webhook verifier'ını içerir. Generic factory yok —
 * MarketplaceClientFactory match expression ile doğru sınıfı döner.
 *
 * Bu modül hiçbir başka modüle (Tenant/Product) bağımlı değildir — girdi/çıktı
 * sadece DTO'lardır (bkz. Modules\Marketplace\DTOs).
 */
interface MarketplaceClient
{
    /**
     * Provider'ın kısa kodu (factory match key'i): 'trendyol' | 'hepsiburada' | 'n11' | 'ciceksepeti'.
     */
    public function code(): string;

    /**
     * Ürünü provider'a push et. Listing kaydı varsa update, yoksa create.
     */
    public function pushProduct(ProductPushDTO $product): PushResult;

    /**
     * Verilen tarihten beri açılmış siparişleri çek. Provider'a özel ham veri DTO'ya map'lenir.
     *
     * @return Collection<int, \Modules\Marketplace\DTOs\MarketplaceOrderDTO>
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
