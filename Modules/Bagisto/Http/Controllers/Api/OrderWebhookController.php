<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Bagisto\Services\BagistoOrderSyncService;

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
    public function __construct(private BagistoOrderSyncService $sync) {}

    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $data = (array) $request->input('data', []);

        return match ($event) {
            'order.created' => $this->handleCreated($data),
            'order.cancelled' => $this->handleCancelled($data),
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
