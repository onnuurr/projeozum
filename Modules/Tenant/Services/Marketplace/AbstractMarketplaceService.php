<?php

namespace Modules\Tenant\Services\Marketplace;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\MarketplaceCommissionRate;
use Modules\Tenant\Models\MarketplaceExpense;
use Modules\Tenant\Models\MarketplaceSale;
use Modules\Tenant\Models\MarketplaceSyncLog;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;
use Modules\Tenant\Services\Marketplace\Contracts\MarketplaceClient;
use Modules\Tenant\Services\Marketplace\DTOs\MarketplaceOrderDTO;
use Modules\Tenant\Services\Marketplace\DTOs\MarketplaceOrderLineDTO;

/**
 * Provider-agnostic persistence çekirdeği. Tüm provider service'leri bunu extend eder;
 * pushProduct/fetchOrders/fetchReports/verifyWebhook abstract — her provider zorunlu yazar.
 *
 * Burada olan: marketplace_sales upsert, marketplace_expenses kayıt, sync_log lifecycle,
 * commission lookup (category-specific → default fallback).
 */
abstract class AbstractMarketplaceService implements MarketplaceClient
{
    public function __construct(
        protected TenantMarketplaceCredential $credential,
    ) {}

    public function tenant(): Tenant
    {
        return $this->credential->tenant;
    }

    /**
     * Sipariş satırı upsert. external_id unique olduğundan idempotent.
     * Komisyon DTO'da gelmemişse commission_rate lookup'tan hesaplanır.
     */
    public function recordSale(MarketplaceOrderDTO $order, MarketplaceOrderLineDTO $line): MarketplaceSale
    {
        return DB::transaction(function () use ($order, $line) {
            $commission = $line->commission;
            $shippingFee = $line->shippingFee;

            // Komisyon DTO'da yoksa lookup (provider rapor çekene kadar tahmin).
            if ($commission <= 0 && $line->productId !== null) {
                $product = Product::find($line->productId);
                if ($product) {
                    [$commissionRate, $shippingRate] = $this->lookupRates($order->marketplace, (int) ($product->category_id ?? 0));
                    $commission  = round(($line->soldPrice * $line->qty) * ($commissionRate / 100), 2);
                    if ($shippingFee <= 0) {
                        $shippingFee = round(($line->soldPrice * $line->qty) * ($shippingRate / 100), 2);
                    }
                }
            }

            $netRevenue = ($line->soldPrice * $line->qty) - $commission - $shippingFee;

            $sale = MarketplaceSale::updateOrCreate(
                [
                    'tenant_id'         => $this->tenant()->id,
                    'marketplace'       => $order->marketplace,
                    'external_order_id' => $order->externalOrderId,
                    'external_line_id'  => $line->externalLineId,
                ],
                [
                    'product_id'   => $line->productId,
                    'sold_price'   => $line->soldPrice,
                    'qty'          => $line->qty,
                    'commission'   => $commission,
                    'shipping_fee' => $shippingFee,
                    'net_revenue'  => $netRevenue,
                    'status'       => $order->status,
                    'raw_payload'  => array_merge($line->raw, ['_order' => $order->raw]),
                    'sold_at'      => Carbon::instance($order->soldAt),
                    'synced_at'    => now(),
                ],
            );

            // Komisyon/kargo expense satırları (sale başına idempotent için önce sil → tekrar yaz).
            MarketplaceExpense::where('marketplace_sale_id', $sale->id)->delete();
            if ($commission > 0) {
                MarketplaceExpense::create([
                    'tenant_id'           => $this->tenant()->id,
                    'marketplace'         => $order->marketplace,
                    'expense_type'        => MarketplaceExpense::TYPE_COMMISSION,
                    'marketplace_sale_id' => $sale->id,
                    'amount'              => $commission,
                    'occurred_at'         => Carbon::instance($order->soldAt),
                ]);
            }
            if ($shippingFee > 0) {
                MarketplaceExpense::create([
                    'tenant_id'           => $this->tenant()->id,
                    'marketplace'         => $order->marketplace,
                    'expense_type'        => MarketplaceExpense::TYPE_SHIPPING,
                    'marketplace_sale_id' => $sale->id,
                    'amount'              => $shippingFee,
                    'occurred_at'         => Carbon::instance($order->soldAt),
                ]);
            }

            return $sale;
        });
    }

    /**
     * Sync log lifecycle helper. Jobs içinde:
     *   $log = $this->startSyncLog('pull_orders');
     *   try { ... $this->finishSyncLog($log, ['items_processed' => $n]); }
     *   catch (\Throwable $e) { $this->failSyncLog($log, $e->getMessage()); throw $e; }
     */
    public function startSyncLog(string $operation): MarketplaceSyncLog
    {
        return MarketplaceSyncLog::create([
            'tenant_id'   => $this->tenant()->id,
            'marketplace' => $this->code(),
            'operation'   => $operation,
            'status'      => MarketplaceSyncLog::STATUS_RUNNING,
            'started_at'  => now(),
        ]);
    }

    public function finishSyncLog(MarketplaceSyncLog $log, array $attributes = []): void
    {
        $log->update(array_merge([
            'status'      => MarketplaceSyncLog::STATUS_SUCCESS,
            'finished_at' => now(),
        ], $attributes));
    }

    public function failSyncLog(MarketplaceSyncLog $log, string $error): void
    {
        $log->update([
            'status'        => MarketplaceSyncLog::STATUS_FAILED,
            'error_message' => mb_substr($error, 0, 1000),
            'finished_at'   => now(),
        ]);
    }

    /**
     * Komisyon ve kargo oranı lookup'ı: önce kategori-spesifik kayıt, yoksa default (category_id NULL).
     *
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    protected function lookupRates(string $marketplace, int $categoryId): array
    {
        $today = now()->toDateString();

        $row = MarketplaceCommissionRate::query()
            ->where('marketplace', $marketplace)
            ->where('category_id', $categoryId)
            ->where('valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $row) {
            $row = MarketplaceCommissionRate::query()
                ->where('marketplace', $marketplace)
                ->whereNull('category_id')
                ->where('valid_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                })
                ->orderByDesc('valid_from')
                ->first();
        }

        if (! $row) {
            return [0.0, 0.0];
        }

        return [(float) $row->commission_rate, (float) $row->shipping_rate];
    }
}
