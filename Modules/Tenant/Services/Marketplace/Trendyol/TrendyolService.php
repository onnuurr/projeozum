<?php

namespace Modules\Tenant\Services\Marketplace\Trendyol;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

/**
 * Trendyol live adapter. Gerçek API çağrıları.
 *
 * Driver = 'live' iken MarketplaceServiceResolver bunu döner; 'stub' iken TrendyolStubService.
 */
class TrendyolService extends AbstractMarketplaceService
{
    public function code(): string
    {
        return 'trendyol';
    }

    public function pushProduct(Product $product): PushResult
    {
        $http = new TrendyolHttpClient($this->credential);
        $mapper = new TrendyolProductMapper();

        $product->loadMissing(['variants', 'category', 'brand']);

        // Trendyol kategori/marka ID'leri category_marketplace_mappings'ten gelir.
        $mapping = $this->resolveCategoryMapping((int) $product->category_id);
        $payload = $mapper->toPushPayload($product, $mapping['external_id'] ?? null, null);

        $response = $http->ensureSuccess(
            $http->http()->post($http->path('/sapigw/suppliers/{supplierId}/v2/products'), $payload),
            'pushProduct',
        );

        $body = $response->json();

        return new PushResult(
            success: true,
            pushed: count($payload['items']),
            externalIds: [(string) $product->id => (string) ($body['batchRequestId'] ?? '')],
        );
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        $http = new TrendyolHttpClient($this->credential);
        $mapper = new TrendyolOrderMapper();

        $response = $http->ensureSuccess(
            $http->http()->get($http->path('/sapigw/suppliers/{supplierId}/orders'), [
                'startDate' => Carbon::instance($since)->getTimestampMs(),
                'endDate'   => now()->getTimestampMs(),
                'size'      => 200,
            ]),
            'fetchOrders',
        );

        $payload = $response->json();
        $orders  = collect($payload['content'] ?? [])
            ->map(fn (array $row) => $mapper->fromArray($row));

        return $orders;
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        // Trendyol finansal raporları için ayrı endpoint (statements / settlements).
        // İlk teslimde sade: orders sum'ından commission/shipping toplamı çıkarılır;
        // ileride statements endpoint'i ile değiştirilir.
        $orders = $this->fetchOrders($from);
        $totalRev = 0.0; $totalCom = 0.0; $totalShip = 0.0;
        foreach ($orders as $o) {
            foreach ($o->lines as $line) {
                $totalRev  += $line->soldPrice * $line->qty;
                $totalCom  += $line->commission;
                $totalShip += $line->shippingFee;
            }
        }

        return new ReportResult(
            from: \DateTimeImmutable::createFromInterface($from),
            to: \DateTimeImmutable::createFromInterface($to),
            totalRevenue: $totalRev,
            totalCommission: $totalCom,
            totalShipping: $totalShip,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return (new TrendyolWebhookVerifier())->verify($request, $this->credential);
    }

    private function resolveCategoryMapping(int $localCategoryId): array
    {
        if ($localCategoryId === 0) {
            return [];
        }

        $row = \DB::table('category_marketplace_mappings')
            ->join('marketplaces', 'marketplaces.id', '=', 'category_marketplace_mappings.marketplace_id')
            ->where('category_id', $localCategoryId)
            ->where('marketplaces.slug', 'trendyol')
            ->select('category_marketplace_mappings.external_id', 'category_marketplace_mappings.category_path')
            ->first();

        return $row ? (array) $row : [];
    }
}
