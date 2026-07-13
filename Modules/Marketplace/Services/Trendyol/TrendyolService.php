<?php

namespace Modules\Marketplace\Services\Trendyol;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

/**
 * Trendyol live adapter. Gerçek API çağrıları.
 *
 * Driver = 'live' iken MarketplaceClientFactory bunu döner; 'stub' iken TrendyolStubService.
 */
class TrendyolService extends AbstractMarketplaceClient
{
    public function code(): string
    {
        return 'trendyol';
    }

    public function pushProduct(ProductPushDTO $product): PushResult
    {
        $http   = new TrendyolHttpClient($this->credentials());
        $mapper = new TrendyolProductMapper();

        $payload = $mapper->toPushPayload($product);

        $response = $http->ensureSuccess(
            $http->http()->post($http->path('/sapigw/suppliers/{supplierId}/v2/products'), $payload),
            'pushProduct',
        );

        $body = $response->json();

        return new PushResult(
            success: true,
            pushed: count($payload['items']),
            externalIds: [(string) $product->productId => (string) ($body['batchRequestId'] ?? '')],
        );
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        $http   = new TrendyolHttpClient($this->credentials());
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

        return collect($payload['content'] ?? [])
            ->map(fn (array $row) => $mapper->fromArray($row));
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
        return (new TrendyolWebhookVerifier())->verify($request, $this->credentials());
    }
}
