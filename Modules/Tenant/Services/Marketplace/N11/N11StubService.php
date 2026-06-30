<?php

namespace Modules\Tenant\Services\Marketplace\N11;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

class N11StubService extends AbstractMarketplaceService
{
    public function code(): string { return 'n11'; }

    public function pushProduct(Product $product): PushResult
    {
        return new PushResult(true, 1, [(string) $product->id => 'N11-STUB-' . uniqid()]);
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
        return $request->header('X-N11-Signature') !== null;
    }
}
