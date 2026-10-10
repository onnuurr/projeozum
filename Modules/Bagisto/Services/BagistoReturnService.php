<?php

namespace Modules\Bagisto\Services;

use App\Contracts\OrderInvoiceLookup;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Bagisto\Exceptions\OrderReturnException;
use Modules\Bagisto\Jobs\NotifyTenantOfReturn;
use Modules\Bagisto\Jobs\PushReturnStateToBagisto;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderReturn;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantCreditService;
use RuntimeException;

/**
 * Bayi iade talepleri: Bagisto RMA'sının SaaS'taki yönetimi.
 *
 * Görev paylaşımı (kullanıcı kararı, 2026-09-26):
 *  - Bagisto: talebin açılması (bayi portalı), atölye ekspertizi (admin RMA ekranı),
 *    iade faturasının yüklenmesi (bayi portalı → ReturnInvoiceApiController).
 *  - SaaS: onay/red kararı, iade faturası kontrolü, para iadesi. Karar Bagisto'ya geri
 *    gönderilir (PushReturnStateToBagisto), bayi durumu portalda görür.
 *
 * Para iadesi ödemenin geldiği yola yapılır: kart → iyzico (Bagisto üzerinden), cariden
 * çekilmiş sipariş → cariye alacak, diğerleri → havale (dekont no ile manuel kayıt).
 * Sipariş bizim tarafımızda faturalandırılmışsa iade faturası kabul edilmeden para iadesi yapılamaz.
 */
class BagistoReturnService
{
    /** Bagisto DefaultRMAStatusEnum: CANCELED / ITEM_CANCELED. */
    private const BAGISTO_CANCELLED_STATUS_IDS = [8, 9];

    public function __construct(
        private TenantCreditService $credit,
        private BagistoSyncClient $client,
        private BagistoOrderSyncService $orders,
    ) {}

    /**
     * Bagisto'nun gönderdiği RMA anlık görüntüsünü kaydeder (return.created / status_updated /
     * inspection_saved hepsi aynı tam görüntüyü taşır, idempotent). SaaS kararı verilmiş kayıtta
     * yalnız talep/ekspertiz alanları güncellenir; karar Bagisto tarafından ezilemez.
     */
    public function upsertFromBagisto(array $data): OrderReturn
    {
        return DB::transaction(function () use ($data) {
            $return = OrderReturn::query()
                ->where('bagisto_rma_id', (string) $data['rma_id'])
                ->lockForUpdate()
                ->first() ?? new OrderReturn(['bagisto_rma_id' => (string) $data['rma_id']]);

            $order = $this->orders->findByBagistoOrderId((string) $data['order_id']);
            $tenant = $order?->tenant
                ?? (! empty($data['customer_email']) ? $this->orders->resolveTenantForEmail($data['customer_email']) : null);

            $items = $this->normalizeItems($order, $data['items'] ?? []);

            $return->fill([
                'order_id' => $order?->id,
                'tenant_id' => $tenant?->id,
                'bagisto_order_id' => (string) $data['order_id'],
                'bagisto_status' => $data['status_title'] ?? null,
                'reason' => $data['reason'] ?? null,
                'resolution' => $data['resolution'] ?? null,
                'information' => $data['information'] ?? null,
                'cargo_method' => $data['cargo_method'] ?? null,
                'items' => $items,
                'images' => array_values(array_filter((array) ($data['images'] ?? []))),
                'inspection' => $data['inspection'] ?? $return->inspection,
                'requested_amount' => round(collect($items)->sum('line_total'), 2),
                'bagisto_created_at' => $data['created_at'] ?? $return->bagisto_created_at,
            ]);

            if (! $return->exists) {
                $return->status = OrderReturn::STATUS_REQUESTED;
            }

            // Bayi talebi Bagisto'da iptal ettiyse: para iadesi yapılmadıysa SaaS kaydı da kapanır.
            if (in_array((int) ($data['status_id'] ?? 0), self::BAGISTO_CANCELLED_STATUS_IDS, true)
                && in_array($return->status, [OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_APPROVED], true)) {
                $return->status = OrderReturn::STATUS_CANCELLED;
            }

            $return->save();

            return $return;
        });
    }

    public function approve(OrderReturn $return, float $amount, ?string $note, User $by): OrderReturn
    {
        $return = DB::transaction(function () use ($return, $amount, $note, $by) {
            $locked = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            $this->assertStatus($locked, [OrderReturn::STATUS_REQUESTED]);

            if (! $locked->order_id) {
                throw new OrderReturnException('İade edilen sipariş SaaS\'ta bulunamadı; karar verilemez.');
            }

            $max = $this->refundableCap($locked);
            if ($amount <= 0 || $amount > $max) {
                throw new OrderReturnException(sprintf('İade tutarı 0 ile %s ₺ arasında olmalı.', number_format($max, 2, ',', '.')));
            }

            $invoiceRequired = $this->orderIsInvoiced($locked->order_id);

            $locked->update([
                'status' => OrderReturn::STATUS_APPROVED,
                'approved_amount' => round($amount, 2),
                'decision_note' => $note,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'invoice_required' => $invoiceRequired,
                'invoice_status' => $invoiceRequired ? OrderReturn::INVOICE_AWAITING : null,
            ]);

            return $locked;
        });

        $this->afterChange($return, NotifyTenantOfReturn::EVENT_APPROVED);

        return $return;
    }

    public function reject(OrderReturn $return, string $note, User $by): OrderReturn
    {
        $return = DB::transaction(function () use ($return, $note, $by) {
            $locked = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            $this->assertStatus($locked, [OrderReturn::STATUS_REQUESTED]);

            $locked->update([
                'status' => OrderReturn::STATUS_REJECTED,
                'decision_note' => $note,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            return $locked;
        });

        $this->afterChange($return, NotifyTenantOfReturn::EVENT_REJECTED);

        return $return;
    }

    /** Bayi portalından gelen iade faturası (ilk yükleme veya reddedilen faturanın yenisi). */
    public function recordInvoice(OrderReturn $return, UploadedFile $file, string $invoiceNo, string $invoiceDate, float $invoiceAmount): OrderReturn
    {
        $return = DB::transaction(function () use ($return, $file, $invoiceNo, $invoiceDate, $invoiceAmount) {
            $locked = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            $this->assertStatus($locked, [OrderReturn::STATUS_APPROVED]);

            if (! $locked->invoice_required
                || ! in_array($locked->invoice_status, [OrderReturn::INVOICE_AWAITING, OrderReturn::INVOICE_REJECTED], true)) {
                throw new OrderReturnException('Bu iade için bekleyen bir fatura yüklemesi yok.');
            }

            $previous = $locked->invoice_path;

            $locked->update([
                'invoice_status' => OrderReturn::INVOICE_PENDING,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'invoice_amount' => round($invoiceAmount, 2),
                'invoice_path' => $file->store('order-returns/'.$locked->id, 'local'),
                'invoice_uploaded_at' => now(),
                'invoice_review_note' => null,
                'invoice_reviewed_by' => null,
                'invoice_reviewed_at' => null,
            ]);

            if ($previous) {
                Storage::disk('local')->delete($previous);
            }

            return $locked;
        });

        PushReturnStateToBagisto::dispatch($return->id);

        return $return;
    }

    public function reviewInvoice(OrderReturn $return, bool $accept, ?string $note, User $by): OrderReturn
    {
        if (! $accept && blank($note)) {
            throw new OrderReturnException('Faturayı reddederken bayiye iletilecek bir açıklama yazın.');
        }

        $return = DB::transaction(function () use ($return, $accept, $note, $by) {
            $locked = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            $this->assertStatus($locked, [OrderReturn::STATUS_APPROVED]);

            if ($locked->invoice_status !== OrderReturn::INVOICE_PENDING) {
                throw new OrderReturnException('Kontrol bekleyen bir iade faturası yok.');
            }

            $locked->update([
                'invoice_status' => $accept ? OrderReturn::INVOICE_ACCEPTED : OrderReturn::INVOICE_REJECTED,
                'invoice_review_note' => $note,
                'invoice_reviewed_by' => $by->id,
                'invoice_reviewed_at' => now(),
            ]);

            return $locked;
        });

        $accept
            ? PushReturnStateToBagisto::dispatch($return->id)
            : $this->afterChange($return, NotifyTenantOfReturn::EVENT_INVOICE_REJECTED);

        return $return;
    }

    /**
     * Onaylı iadenin parasını ödemenin geldiği yola geri öder. Havale yolunda gerçek transfer
     * SaaS dışında yapılır; $reference (dekont / EFT sorgu no) zorunludur.
     */
    public function refund(OrderReturn $return, User $by, ?string $reference = null): OrderReturn
    {
        $return = DB::transaction(function () use ($return, $by, $reference) {
            $locked = OrderReturn::query()->with('order')->lockForUpdate()->findOrFail($return->id);

            $this->assertStatus($locked, [OrderReturn::STATUS_APPROVED]);

            if (! $locked->invoiceCleared()) {
                throw new OrderReturnException('Sipariş faturalandırılmış: iade faturası kabul edilmeden ödeme yapılamaz.');
            }

            $amount = (float) $locked->approved_amount;
            $method = $this->refundMethodFor($locked);

            $refundReference = match ($method) {
                OrderReturn::REFUND_CARI => $this->refundToCari($locked, $amount, $by),
                OrderReturn::REFUND_CARD => $this->refundToCard($locked, $amount),
                OrderReturn::REFUND_BANK => filled($reference)
                    ? trim($reference)
                    : throw new OrderReturnException('Havale ile iadede dekont / işlem numarası zorunludur.'),
            };

            $locked->update([
                'status' => OrderReturn::STATUS_REFUNDED,
                'refund_method' => $method,
                'refund_reference' => $refundReference,
                'refunded_by' => $by->id,
                'refunded_at' => now(),
            ]);

            return $locked;
        });

        $this->afterChange($return, NotifyTenantOfReturn::EVENT_REFUNDED);

        return $return;
    }

    /**
     * Ödemenin geldiği yol: kartla ödenmişse kart; cariden net çekim varsa cari; aksi halde havale
     * (Bagisto `moneytransfer` / kapıda ödeme ya da kredi yetersizliğinden cariye yazılamamış sipariş).
     */
    public function refundMethodFor(OrderReturn $return): string
    {
        $order = $return->order;

        if ($order?->payment_method === 'card') {
            return OrderReturn::REFUND_CARD;
        }

        $tenant = $this->tenantOf($return);

        if ($order && $tenant && $this->credit->netChargedForOrder($tenant, $order->id) > 0) {
            return OrderReturn::REFUND_CARI;
        }

        return OrderReturn::REFUND_BANK;
    }

    /** İadenin bayisi (OrderReturn'de tenant() ilişkisi yok, bkz. model notu). */
    public function tenantOf(OrderReturn $return): ?Tenant
    {
        return $return->tenant_id ? Tenant::find($return->tenant_id) : null;
    }

    /** Aynı siparişin diğer (reddedilmemiş) iadeleri düşüldükten sonra onaylanabilecek üst sınır. */
    public function refundableCap(OrderReturn $return): float
    {
        $order = $return->order ?? Order::find($return->order_id);
        if (! $order) {
            return 0.0;
        }

        $alreadyApproved = (float) OrderReturn::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($return->id)
            ->whereIn('status', [OrderReturn::STATUS_APPROVED, OrderReturn::STATUS_REFUNDED])
            ->sum('approved_amount');

        return max(0.0, round((float) $order->total - $alreadyApproved, 2));
    }

    public function orderIsInvoiced(int $orderId): bool
    {
        // Finance yüklü değilse (binding yok) güvenli taraf: fatura şartı aranır.
        if (! app()->bound(OrderInvoiceLookup::class)) {
            return true;
        }

        return app(OrderInvoiceLookup::class)->hasIssuedInvoice($orderId);
    }

    /** Bagisto'ya geri gönderilen SaaS durumu (bayi portalı bunu gösterir). */
    public function statePayload(OrderReturn $return): array
    {
        return [
            'rma_id' => $return->bagisto_rma_id,
            'status' => $return->status,
            'status_label' => OrderReturn::statuses()[$return->status] ?? $return->status,
            'decision_note' => $return->decision_note,
            'approved_amount' => $return->approved_amount !== null ? (float) $return->approved_amount : null,
            'invoice_required' => $return->invoice_required,
            'invoice_status' => $return->invoice_status,
            'invoice_review_note' => $return->invoice_review_note,
            'refund_method' => $return->refund_method,
            'refund_method_label' => $return->refund_method ? (OrderReturn::refundMethods()[$return->refund_method] ?? null) : null,
            'refunded_at' => optional($return->refunded_at)->toIso8601String(),
        ];
    }

    private function refundToCari(OrderReturn $return, float $amount, User $by): string
    {
        $tenant = $this->tenantOf($return);
        $net = $this->credit->netChargedForOrder($tenant, $return->order_id);
        if ($amount > $net) {
            throw new OrderReturnException(sprintf('Cari iade tutarı, siparişten cariye yazılan net tutarı (%s ₺) aşamaz.', number_format($net, 2, ',', '.')));
        }

        $ledger = $this->credit->credit(
            tenant: $tenant,
            amount: $amount,
            reason: 'bagisto_return',
            orderId: $return->order_id,
            byUserId: $by->id,
        );

        return 'cari-ledger:'.$ledger->id;
    }

    /**
     * iyzico kimlik bilgileri Bagisto'da; iade Bagisto'nun SaasSync refund ucundan yapılır.
     * Hata halinde transaction geri alınır, iade "onaylı" durumda kalır ve tekrar denenebilir.
     */
    private function refundToCard(OrderReturn $return, float $amount): string
    {
        try {
            $data = $this->client->request('api/saas-sync/refunds', [
                'bagisto_order_id' => $return->bagisto_order_id,
                'rma_id' => $return->bagisto_rma_id,
                'amount' => $amount,
            ]);
        } catch (RequestException $e) {
            $message = $e->response?->json('message') ?: $e->getMessage();
            Log::warning('Bagisto iade: karta iade başarısız.', ['order_return_id' => $return->id, 'error' => $message]);

            throw new OrderReturnException('Karta iade yapılamadı: '.$message, previous: $e);
        } catch (RuntimeException $e) {
            throw new OrderReturnException('Karta iade yapılamadı: '.$e->getMessage(), previous: $e);
        }

        if (empty($data['refund_id'])) {
            throw new OrderReturnException('Karta iade yanıtı beklenmedik: işlem numarası dönmedi.');
        }

        return 'iyzico:'.$data['refund_id'];
    }

    private function afterChange(OrderReturn $return, string $event): void
    {
        PushReturnStateToBagisto::dispatch($return->id);
        NotifyTenantOfReturn::dispatch($return->id, $event);
    }

    private function assertStatus(OrderReturn $return, array $allowed): void
    {
        if (! in_array($return->status, $allowed, true)) {
            throw new OrderReturnException(sprintf(
                'Bu işlem "%s" durumundaki iade için yapılamaz.',
                OrderReturn::statuses()[$return->status] ?? $return->status,
            ));
        }
    }

    /**
     * Bagisto kalemlerini SaaS sipariş satırlarıyla eşler; tutar bayinin ödediği toptan birim
     * fiyattan, KDV dahil hesaplanır (Bagisto'nun perakende fiyatından değil).
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(?Order $order, array $items): array
    {
        $orderItems = $order?->items()->get() ?? collect();
        $variantIds = ProductVariant::query()
            ->whereIn('sku', collect($items)->pluck('sku')->filter()->all())
            ->pluck('id', 'sku');

        return collect($items)->map(function ($item) use ($orderItems, $variantIds) {
            $qty = (int) ($item['qty'] ?? 0);
            $line = $orderItems->firstWhere('product_variant_id', $variantIds[$item['sku'] ?? ''] ?? -1);
            // İade kartla ödenen KDV dahil tutar üzerinden: satırın KDV hariç fiyatına o günkü oranla KDV eklenir.
            $unitPrice = $line ? round((float) $line->unit_price * (1 + (float) $line->vat_rate / 100), 2) : 0.0;

            return [
                'sku' => $item['sku'] ?? null,
                'name' => $line?->product_name ?? ($item['name'] ?? null),
                'color' => $line?->color,
                'size' => $line?->size,
                'image' => $line?->product_image,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $qty, 2),
            ];
        })->values()->all();
    }
}
