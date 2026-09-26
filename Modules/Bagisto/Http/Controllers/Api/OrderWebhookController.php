<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Bagisto\Services\BagistoOrderSyncService;
use Modules\Bagisto\Services\BagistoReturnService;

/**
 * Bagisto tarafındaki `Webkul\SaasSync\Jobs\PushEventToSaas` job'ının hedefi.
 *
 * Tek bir Bagisto müşteri hesabı = tek bir tenant (e-posta üzerinden eşleşir,
 * bkz. `Webkul\SaasSync\Http\Controllers\TenantWebhookController`). Bagisto'da
 * satış gerçekleştiğinde bu, o tenant adına açılmış bir dropship siparişi gibi
 * kaydedilir: platform stoktan düşer, tenant'ın kredi bakiyesinden toptan
 * maliyet çekilir, teslimat doğrudan Bagisto'daki son müşteriye yapılır.
 *
 * Asıl senkronizasyon mantığı {@see BagistoOrderSyncService}'te — burası
 * yalnızca event dispatch + HTTP giriş/çıkış.
 */
class OrderWebhookController extends Controller
{
    public function __construct(
        private BagistoOrderSyncService $sync,
        private BagistoReturnService $returns,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $data = (array) $request->input('data', []);

        return match ($event) {
            'order.created' => $this->handleCreated($data),
            'order.cancelled' => $this->handleCancelled($data),
            // Üçü de RMA'nın tam anlık görüntüsünü taşır (Webkul\SaasSync RmaSnapshot); kayıt idempotent.
            'return.created', 'return.status_updated', 'return.inspection_saved' => $this->handleReturn($data),
            default => response()->json(['message' => 'Desteklenmeyen event: '.$event], 422),
        };
    }

    protected function handleCreated(array $data): JsonResponse
    {
        $data = Validator::make($data, [
            'order_id' => ['required'],
            'increment_id' => ['nullable', 'string'],
            'customer_email' => ['required', 'email'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric'],
            'shipping_address' => ['nullable', 'array'],
            // Bagisto ödeme yöntemi (iyzico = kart, cari_hesap = cari). charge_credit=false ise
            // (kartla peşin tahsil) sipariş cari hesaba borç yazılmaz. Alan yoksa eski davranış: borç yazılır.
            'payment_method' => ['nullable', 'string', 'max:32'],
            'charge_credit' => ['nullable', 'boolean'],
            // Bayi portalı (ozmbayi) checkout'undaki "Dropship siparişi" kartı: son alıcı.
            // Varsa sipariş bu adrese gönderilir; bayinin kendi adresi değişmez.
            'dropship' => ['nullable', 'array'],
            // Tek ekran checkout (2026-09): dropship'te son alıcı yerine kargo firması + barkod no gelir;
            // alıcı alanları yalnızca eski kayıtlarda dolu. Alıcı yoksa bayinin adresi kullanılır.
            'dropship.carrier' => ['nullable', 'string', 'max:32'],
            'dropship.carrier_label' => ['nullable', 'string', 'max:64'],
            'dropship.tracking_number' => ['nullable', 'string', 'max:100'],
            'dropship.recipient_name' => ['nullable', 'string', 'max:191'],
            'dropship.recipient_phone' => ['required_with:dropship.recipient_name', 'nullable', 'string', 'max:32'],
            'dropship.address' => ['required_with:dropship.recipient_name', 'nullable', 'string', 'max:1000'],
            'dropship.district' => ['nullable', 'string', 'max:100'],
            'dropship.city' => ['required_with:dropship.recipient_name', 'nullable', 'string', 'max:100'],
            'dropship.postcode' => ['nullable', 'string', 'max:16'],
            'dropship.channel' => ['nullable', 'string', 'max:32'],
            'dropship.channel_ref' => ['nullable', 'string', 'max:100'],
            'dropship.packing_note' => ['nullable', 'string', 'max:500'],
            'dropship.label_url' => ['nullable', 'url', 'max:2048'],
        ])->validate();

        $bagistoOrderId = (string) $data['order_id'];

        $existing = $this->sync->findByBagistoOrderId($bagistoOrderId);
        if ($existing) {
            return response()->json([
                'message' => 'Order already synced.',
                'data' => ['id' => $existing->id],
            ]);
        }

        $tenant = $this->sync->resolveTenantForEmail($data['customer_email']);
        if (! $tenant) {
            Log::warning('Bagisto order webhook: e-postayla eşleşen tenant bulunamadı.', [
                'email' => $data['customer_email'],
                'bagisto_order_id' => $bagistoOrderId,
            ]);

            return response()->json(['message' => 'Tenant not found for customer email.'], 404);
        }

        $actorUserId = $tenant->users()->value('id');
        if (! $actorUserId) {
            Log::warning('Bagisto order webhook: tenant için kullanıcı hesabı yok.', [
                'tenant_id' => $tenant->id,
                'bagisto_order_id' => $bagistoOrderId,
            ]);

            return response()->json(['message' => 'Tenant has no user account.'], 404);
        }

        $order = $this->sync->createDropshipOrder($tenant, $actorUserId, $bagistoOrderId, $data);

        return response()->json([
            'message' => 'Order synced.',
            'data' => ['id' => $order->id, 'order_no' => $order->order_no],
        ], 201);
    }

    protected function handleReturn(array $data): JsonResponse
    {
        $data = Validator::make($data, [
            'rma_id' => ['required'],
            'order_id' => ['required'],
            'customer_email' => ['nullable', 'email'],
            'status_id' => ['nullable', 'integer'],
            'status_title' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:64'],
            'information' => ['nullable', 'string', 'max:5000'],
            'cargo_method' => ['nullable', 'string', 'max:32'],
            'items' => ['nullable', 'array'],
            'items.*.sku' => ['nullable', 'string'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:0'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['nullable', 'url', 'max:2048'],
            'inspection' => ['nullable', 'array'],
            'created_at' => ['nullable', 'date'],
        ])->validate();

        $return = $this->returns->upsertFromBagisto($data);

        return response()->json([
            'message' => 'Return synced.',
            'data' => ['id' => $return->id, 'status' => $return->status],
        ]);
    }

    protected function handleCancelled(array $data): JsonResponse
    {
        $data = Validator::make($data, [
            'order_id' => ['required'],
        ])->validate();

        $order = $this->sync->cancelByBagistoOrderId((string) $data['order_id']);

        if (! $order) {
            Log::warning('Bagisto order webhook: bilinmeyen sipariş için iptal bildirimi geldi.', [
                'bagisto_order_id' => $data['order_id'],
            ]);

            return response()->json(['message' => 'Order not found, nothing to cancel.']);
        }

        return response()->json(['message' => 'Cancellation processed.']);
    }
}
