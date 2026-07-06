<?php

namespace Modules\Tenant\Services\Marketplace\N11;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Exceptions\NotSupportedException;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

/** N11 live adapter — iskelet. Doc: https://api.n11.com/ ws-doc. */
class N11Service extends AbstractMarketplaceService
{
    public function code(): string { return 'n11'; }

    public function pushProduct(Product $product): PushResult
    {
        throw NotSupportedException::liveDriverMissing('N11', 'pushProduct');
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        throw NotSupportedException::liveDriverMissing('N11', 'fetchOrders');
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        throw NotSupportedException::liveDriverMissing('N11', 'fetchReports');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
