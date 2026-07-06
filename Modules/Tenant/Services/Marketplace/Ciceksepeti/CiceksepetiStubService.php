<?php

namespace Modules\Tenant\Services\Marketplace\Ciceksepeti;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

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
