<?php

namespace Modules\Marketplace\Services\N11;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

class N11StubService extends AbstractMarketplaceClient
{
    public function code(): string { return 'n11'; }

    public function pushProduct(ProductPushDTO $product): PushResult
    {
        return new PushResult(true, 1, [(string) $product->productId => 'N11-STUB-' . uniqid()]);
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
