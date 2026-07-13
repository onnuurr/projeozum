<?php

namespace Modules\Marketplace\Services\Hepsiburada;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

/**
 * Dev/CI için minimal Hepsiburada stub. Sahte success döner; UI akışlarını test eder.
 */
class HepsiburadaStubService extends AbstractMarketplaceClient
{
    public function code(): string
    {
        return 'hepsiburada';
    }

    public function pushProduct(ProductPushDTO $product): PushResult
    {
        return new PushResult(true, 1, [(string) $product->productId => 'HB-STUB-' . uniqid()]);
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        return collect();
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        return new ReportResult(
            from: \DateTimeImmutable::createFromInterface($from),
            to: \DateTimeImmutable::createFromInterface($to),
            totalRevenue: 0, totalCommission: 0, totalShipping: 0,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return $request->header('X-Hepsiburada-Signature') !== null;
    }
}
