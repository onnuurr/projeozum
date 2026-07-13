<?php

namespace Modules\Marketplace\Services\Trendyol;

use DateTimeImmutable;
use Modules\Marketplace\DTOs\MarketplaceOrderDTO;
use Modules\Marketplace\DTOs\MarketplaceOrderLineDTO;

/**
 * Trendyol order JSON → MarketplaceOrderDTO map'lemesi.
 *
 * Trendyol payload örnek şeması (özet):
 *   { "orderNumber":"...", "status":"Created", "orderDate":1718000000000,
 *     "lines":[ {"id":"...","sku":"...","productName":"...","price":100,"quantity":2,
 *                "commission":15,"shippingFee":5} ] }
 *
 * NOT: productId burada asla çözülmez (bu modül Product Eloquent modeline bağımlı
 * olamaz) — sku üzerinden productId eşlemesi çağıran tarafta
 * (Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService::recordSale) yapılır.
 */
class TrendyolOrderMapper
{
    public function fromArray(array $payload): MarketplaceOrderDTO
    {
        $lines = [];
        foreach ($payload['lines'] ?? [] as $line) {
            $lines[] = $this->lineFromArray($line);
        }

        return new MarketplaceOrderDTO(
            marketplace: 'trendyol',
            externalOrderId: (string) ($payload['orderNumber'] ?? $payload['id'] ?? ''),
            status: $this->mapStatus((string) ($payload['status'] ?? 'Created')),
            soldAt: $this->parseDate($payload['orderDate'] ?? null),
            lines: $lines,
            raw: $payload,
        );
    }

    private function lineFromArray(array $line): MarketplaceOrderLineDTO
    {
        $sku = (string) ($line['sku'] ?? $line['merchantSku'] ?? '');

        return new MarketplaceOrderLineDTO(
            externalLineId: (string) ($line['id'] ?? $line['orderLineId'] ?? ''),
            productId: null,
            sku: $sku !== '' ? $sku : null,
            productName: (string) ($line['productName'] ?? 'Trendyol Ürün'),
            soldPrice: (float) ($line['price'] ?? 0),
            qty: (int) ($line['quantity'] ?? 1),
            commission: (float) ($line['commission'] ?? 0),
            shippingFee: (float) ($line['shippingFee'] ?? 0),
            raw: $line,
        );
    }

    private function mapStatus(string $trendyolStatus): string
    {
        return match (strtolower($trendyolStatus)) {
            'created', 'picking'      => 'new',
            'invoiced', 'shipped'     => 'shipped',
            'delivered'               => 'delivered',
            'cancelled', 'unsupplied' => 'cancelled',
            'returned'                => 'returned',
            default                   => 'new',
        };
    }

    private function parseDate(mixed $value): DateTimeImmutable
    {
        if ($value === null) {
            return new DateTimeImmutable();
        }
        // Trendyol epoch-ms gönderir; integer gibi gelir.
        if (is_int($value) || ctype_digit((string) $value)) {
            $ms = (int) $value;
            return DateTimeImmutable::createFromFormat('U', (string) intdiv($ms, 1000)) ?: new DateTimeImmutable();
        }

        return new DateTimeImmutable((string) $value);
    }
}
