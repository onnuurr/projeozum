<?php

namespace Modules\Marketplace\Services\Trendyol;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

/**
 * Dev/CI için fixture-based Trendyol adapter. Network çağrısı yok.
 *
 * Fixture path: Modules/Marketplace/Resources/fixtures/marketplace/trendyol/{orders,products}.json
 * README: fixture provider doc version'ını pinler (rot önleme).
 */
class TrendyolStubService extends AbstractMarketplaceClient
{
    public function code(): string
    {
        return 'trendyol';
    }

    public function pushProduct(ProductPushDTO $product): PushResult
    {
        // Network yok — başarılı varsayım, batch ID fake.
        return new PushResult(
            success: true,
            pushed: max(1, count($product->variants)),
            externalIds: [(string) $product->productId => 'STUB-BATCH-' . uniqid()],
        );
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        $payload = $this->loadFixture('orders.json');
        $mapper  = new TrendyolOrderMapper();

        return collect($payload['content'] ?? [])
            ->map(fn (array $row) => $mapper->fromArray($row));
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        return new ReportResult(
            from: \DateTimeImmutable::createFromInterface($from),
            to: \DateTimeImmutable::createFromInterface($to),
            totalRevenue: 1500.00,
            totalCommission: 270.00,
            totalShipping: 60.00,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        // Stub: imza yoksa false; varsa kabul (gerçek hesap stub mode'da imza üretmez).
        return $request->header('X-Trendyol-Signature') !== null;
    }

    private function loadFixture(string $file): array
    {
        $path = module_path('Marketplace', 'Resources/fixtures/marketplace/trendyol/' . $file);
        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
}
