<?php

namespace Modules\Marketplace\Services\N11;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

/** N11 live adapter — iskelet. Doc: https://api.n11.com/ ws-doc. */
class N11Service extends AbstractMarketplaceClient
{
    public function code(): string { return 'n11'; }

    public function pushProduct(ProductPushDTO $product): PushResult
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
