<?php

namespace Modules\Marketplace\Services\Ciceksepeti;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Marketplace\Services\AbstractMarketplaceService;
use Modules\Marketplace\Services\DTOs\PushResult;
use Modules\Marketplace\Services\DTOs\ReportResult;

/** Çiçeksepeti live adapter — iskelet. */
class CiceksepetiService extends AbstractMarketplaceService
{
    public function code(): string { return 'ciceksepeti'; }

    public function pushProduct(Product $product): PushResult
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
