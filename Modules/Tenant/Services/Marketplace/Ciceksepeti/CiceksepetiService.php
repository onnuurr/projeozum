<?php

namespace Modules\Tenant\Services\Marketplace\Ciceksepeti;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Exceptions\NotSupportedException;
use Modules\Tenant\Services\Marketplace\AbstractMarketplaceService;
use Modules\Tenant\Services\Marketplace\DTOs\PushResult;
use Modules\Tenant\Services\Marketplace\DTOs\ReportResult;

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
