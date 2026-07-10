<?php

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Modules\Product\Models\CartItem;

/**
 * Terk edilmiş sepet kalemlerini temizler (varsayılan 30 günden eski).
 *
 * Sepet stoğa asla dokunmaz (D1) ve fiyat checkout'ta yeniden çözülür (D4), dolayısıyla
 * bu yalnızca UX/veri hijyeni içindir — bekleyen bir siparişi etkilemez. routes/console.php
 * içinden günlük zamanlanır.
 */
class PruneStaleCartsCommand extends Command
{
    protected $signature = 'product:prune-stale-carts {--days=30 : Bu günden eski sepet kalemleri silinir}';

    protected $description = 'Terk edilmiş (varsayılan 30 günden eski) sepet kalemlerini temizler.';

    public function handle(): int
    {
        $days      = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $deleted = CartItem::query()
            ->where('updated_at', '<', $threshold)
            ->delete();

        $this->info("{$days} günden eski sepet kalemleri temizlendi. Silinen: {$deleted}.");

        return self::SUCCESS;
    }
}
