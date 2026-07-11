<?php

namespace Modules\Marketplace\Services\Trendyol;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Modules\Product\Models\Product;
use Modules\Marketplace\Services\DTOs\MarketplaceOrderDTO;
use Modules\Marketplace\Services\DTOs\MarketplaceOrderLineDTO;

/**
 * Trendyol order JSON → MarketplaceOrderDTO map'lemesi.
 *
 * Trendyol payload örnek şeması (özet):
 *   { "orderNumber":"...", "status":"Created", "orderDate":1718000000000,
 *     "lines":[ {"id":"...","sku":"...","productName":"...","price":100,"quantity":2,
 *                "commission":15,"shippingFee":5} ] }
 *
 * NOT: Gerçek alan isimleri partner doc'tan teyit edilmeli (orderNumber vs id farklı
 * olabilir; status enum'u Trendyol'a özel — burada AbstractMarketplaceService'in
 * beklediği new/shipped/delivered/cancelled/returned'a normalize ediyoruz).
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
        $productId = $sku !== '' ? Product::query()->where('sku', $sku)->value('id') : null;

        return new MarketplaceOrderLineDTO(
            externalLineId: (string) ($line['id'] ?? $line['orderLineId'] ?? ''),
            productId: $productId !== null ? (int) $productId : null,
            sku: $sku ?: null,
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
