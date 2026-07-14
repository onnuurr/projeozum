<?php

namespace Modules\Marketplace\Services\Hepsiburada;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Marketplace\Services\AbstractMarketplaceService;
use Modules\Marketplace\Services\DTOs\PushResult;
use Modules\Marketplace\Services\DTOs\ReportResult;

/**
 * Dev/CI için minimal Hepsiburada stub. Sahte success döner; UI akışlarını test eder.
 */
class HepsiburadaStubService extends AbstractMarketplaceService
{
    public function code(): string
    {
        return 'hepsiburada';
    }

    public function pushProduct(Product $product): PushResult
    {
        return new PushResult(true, 1, [(string) $product->id => 'HB-STUB-' . uniqid()]);
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
