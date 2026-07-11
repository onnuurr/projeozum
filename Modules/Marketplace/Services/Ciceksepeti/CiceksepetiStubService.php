<?php

namespace Modules\Marketplace\Services\Ciceksepeti;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Marketplace\Services\AbstractMarketplaceService;
use Modules\Marketplace\Services\DTOs\PushResult;
use Modules\Marketplace\Services\DTOs\ReportResult;

class CiceksepetiStubService extends AbstractMarketplaceService
{
    public function code(): string { return 'ciceksepeti'; }

    public function pushProduct(Product $product): PushResult
    {
        return new PushResult(true, 1, [(string) $product->id => 'CS-STUB-' . uniqid()]);
    }

    public function fetchOrders(DateTimeInterface $since): Collection { return collect(); }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        return new ReportResult(
            \DateTimeImmutable::createFromInterface($from),
            \DateTimeImmutable::createFromInterface($to),
            0, 0, 0,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return $request->header('X-Ciceksepeti-Signature') !== null;
    }
}
