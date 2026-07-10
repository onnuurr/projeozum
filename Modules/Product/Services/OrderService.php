<?php

namespace Modules\Product\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Product\Exceptions\InvalidOrderTransitionException;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderStatusHistory;
use Modules\Tenant\Services\TenantCreditService;

/**
 * Sipariş yaşam döngüsü orkestrasyonu (D3).
 *
 * `transition()` durum makinesinin tek giriş noktasıdır: harita kontrolü, durum
 * güncellemesi, denetim satırı ve iptal yan etkileri (stok iadesi + kredi iadesi)
 * tek transaction içinde yürütülür.
 */
class OrderService
{
    public function __construct(
        private StockService $stock,
        private TenantCreditService $credit,
    ) {}

    /**
     * Bir siparişi yeni bir duruma geçirir.
     *
     * @throws InvalidOrderTransitionException  geçiş haritada tanımlı değilse.
     */
    public function transition(Order $order, string $to, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note) {
            $from    = $order->status;
            $allowed = Order::allowedTransitions()[$from] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            $order->forceFill(['status' => $to])->save();

            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $from,
                'to_status'   => $to,
                'user_id'     => $actor->id,
                'note'        => $note,
            ]);

            // İptal (yalnız kargolanmadan önce mümkün): stok iadesi + kredi iadesi.
            if ($to === Order::STATUS_CANCELLED) {
                $this->stock->restockForOrder($order);

                if ($order->tenant) {
                    $this->credit->credit(
                        tenant: $order->tenant,
                        amount: (float) $order->total,
                        reason: 'order_cancelled',
                        orderId: $order->id,
                    );
                }
            }

            return $order;
        });
    }
}
