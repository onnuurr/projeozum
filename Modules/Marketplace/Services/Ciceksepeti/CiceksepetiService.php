<?php

namespace Modules\Marketplace\Services\Ciceksepeti;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\PushResult;
use Modules\Marketplace\DTOs\ReportResult;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Marketplace\Services\AbstractMarketplaceClient;

/** Çiçeksepeti live adapter — iskelet. */
class CiceksepetiService extends AbstractMarketplaceClient
{
    public function code(): string { return 'ciceksepeti'; }

    public function pushProduct(ProductPushDTO $product): PushResult
    {
        throw NotSupportedException::liveDriverMissing('Çiçeksepeti', 'pushProduct');
    }

    public function fetchOrders(DateTimeInterface $since): Collection
    {
        throw NotSupportedException::liveDriverMissing('Çiçeksepeti', 'fetchOrders');
    }

    public function fetchReports(DateTimeInterface $from, DateTimeInterface $to): ReportResult
    {
        throw NotSupportedException::liveDriverMissing('Çiçeksepeti', 'fetchReports');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
