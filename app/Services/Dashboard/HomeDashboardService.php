<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\PatternEngine\Models\ConversionJob;
use Modules\PatternEngine\Models\Pattern;
use Modules\Product\Models\Order;
use Modules\Product\Models\Product;
use Modules\Product\Models\Stock;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;

/**
 * Ana sayfa (/dashboard) özeti. Modüller arası salt-okuma sayımlar tek yerde toplanır —
 * `app/` katmanı deptrac'ta tüm modüllere erişebilen kompozisyon kökü olduğu için burada.
 *
 * Her bölüm kullanıcının ilgili görüntüleme yetkisine bağlıdır; yetkisi olmayan bölüm
 * hiç sorgulanmaz ve yanıtta `null` döner (ön yüz o kartı çizmez).
 */
class HomeDashboardService
{
    /** Sipariş grafiği kaç günü kapsar. */
    public const TREND_DAYS = 30;

    /** "Açık" sayılan sipariş durumları (henüz kargoya verilmemiş). */
    private const OPEN_ORDER_STATUSES = [
        Order::STATUS_PENDING,
        Order::STATUS_CONFIRMED,
        Order::STATUS_PREPARING,
    ];

    public function build(User $user): array
    {
        $orders = $user->can('order.view') ? $this->orders() : null;

        return [
            'orders'    => $orders,
            'tenants'   => $user->can('tenant.view') ? $this->tenants() : null,
            'stock'     => $user->can('product.view') ? $this->stock() : null,
            'atelier'   => $user->can('atelier.view') ? $this->atelier() : null,
            'patterns'  => $user->can('atelier.pattern.view') ? $this->patterns() : null,
            'catalog'   => $this->catalog($user),
            'attention' => $this->attention($user, $orders),
        ];
    }

    private function orders(): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $trendStart = Carbon::today()->subDays(self::TREND_DAYS - 1);

        $statusCounts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($c) => (int) $c);

        // Günlük sipariş adedi + ciro (iptaller ciroya katılmaz); boş günler 0 ile doldurulur.
        $daily = Order::query()
            ->where('created_at', '>=', $trendStart)
            ->selectRaw('date(created_at) as day, count(*) as orders')
            ->selectRaw('sum(case when status <> ? then total else 0 end) as revenue', [Order::STATUS_CANCELLED])
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $trend = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $day = $trendStart->copy()->addDays($i)->toDateString();
            $trend[] = [
                'date'    => $day,
                'orders'  => (int) ($daily[$day]->orders ?? 0),
                'revenue' => round((float) ($daily[$day]->revenue ?? 0), 2),
            ];
        }

        $recent = Order::query()
            ->with('tenant:id,name')
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'order_no', 'status', 'total', 'tenant_id', 'created_at'])
            ->map(fn (Order $o) => [
                'id'         => $o->id,
                'order_no'   => $o->order_no,
                'status'     => $o->status,
                'total'      => (float) $o->total,
                'tenant'     => $o->tenant?->name,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ]);

        return [
            'open'          => $statusCounts->only(self::OPEN_ORDER_STATUSES)->sum(),
            'pending'       => $statusCounts->get(Order::STATUS_PENDING, 0),
            'month_count'   => Order::query()->where('created_at', '>=', $monthStart)->count(),
            'month_revenue' => round((float) Order::query()
                ->where('created_at', '>=', $monthStart)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->sum('total'), 2),
            'status_counts' => $statusCounts,
            'statuses'      => Order::statuses(),
            'trend'         => $trend,
            'recent'        => $recent,
        ];
    }

    private function tenants(): array
    {
        return [
            'active'               => Tenant::query()->where('is_active', true)->count(),
            'total'                => Tenant::query()->count(),
            'pending_applications' => TenantApplication::query()->where('status', TenantApplication::STATUS_PENDING)->count(),
        ];
    }

    private function stock(): array
    {
        return [
            'critical'   => Stock::query()->belowMin()->count(),
            'warehouses' => DB::table('warehouses')->count(),
        ];
    }

    private function atelier(): array
    {
        $open = [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS];

        return [
            'active' => ProductionOrder::query()->whereIn('status', $open)->count(),
            'late'   => ProductionOrder::query()
                ->whereIn('status', $open)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today())
                ->count(),
            'draft'  => ProductionOrder::query()->where('status', ProductionOrder::STATUS_DRAFT)->count(),
        ];
    }

    private function patterns(): array
    {
        return [
            'total'           => Pattern::query()->count(),
            'approved'        => Pattern::query()->where('status', Pattern::STATUS_APPROVED)->count(),
            'needs_work'      => Pattern::query()->whereIn('extraction_status', [
                Pattern::EXTRACTION_FAILED, Pattern::EXTRACTION_NEEDS_TRACING,
            ])->count(),
            'conversions_review' => ConversionJob::query()->where('status', ConversionJob::STATUS_NEEDS_REVIEW)->count(),
        ];
    }

    /** Katalog/varlık sayıları — yalnızca kullanıcının görebildiği modüller. */
    private function catalog(User $user): array
    {
        $items = [];
        if ($user->can('product.view')) {
            $items[] = ['key' => 'products', 'label' => 'Ürün', 'value' => Product::query()->count(), 'to' => '/products', 'icon' => 'package'];
        }
        if ($user->can('atelier.pattern.view')) {
            $items[] = ['key' => 'patterns', 'label' => 'Kalıp', 'value' => Pattern::query()->count(), 'to' => '/atelier/patterns', 'icon' => 'layers'];
        }
        if ($user->can('creative.view')) {
            $items[] = ['key' => 'mannequins', 'label' => 'Sanal Manken', 'value' => Mannequin::query()->count(), 'to' => '/creative/mannequins', 'icon' => 'user'];
            $items[] = ['key' => 'poses', 'label' => 'Poz', 'value' => Pose::query()->count(), 'to' => '/creative/poses', 'icon' => 'sparkles'];
        }
        if ($user->can('tenant.view')) {
            $items[] = ['key' => 'tenants', 'label' => 'İş Ortağı', 'value' => Tenant::query()->count(), 'to' => '/tenants', 'icon' => 'users'];
        }

        return $items;
    }

    /**
     * Aksiyon gerektiren durumlar (yalnızca sıfırdan büyük olanlar, yetkiye göre).
     *
     * @return list<array{key: string, title: string, count: int, to: string, tone: string, icon: string}>
     */
    private function attention(User $user, ?array $orders): array
    {
        $items = [];
        $add = function (string $key, string $title, int $count, string $to, string $tone, string $icon) use (&$items) {
            if ($count > 0) {
                $items[] = compact('key', 'title', 'count', 'to', 'tone', 'icon');
            }
        };

        if ($orders !== null) {
            $add('orders_pending', 'Onay bekleyen sipariş', $orders['pending'], '/orders?status=pending', 'warning', 'cart');
        }
        if ($user->can('tenant.view')) {
            $add('applications', 'İncelenmeyi bekleyen iş ortağı başvurusu', TenantApplication::query()->where('status', TenantApplication::STATUS_PENDING)->count(), '/tenants/applications', 'info', 'user-plus');
        }
        if ($user->can('product.view')) {
            $add('critical_stock', 'Minimum seviyenin altında stok', Stock::query()->belowMin()->count(), '/products/stocks?critical_only=1', 'danger', 'warehouse');
        }
        if ($user->can('atelier.view')) {
            $open = [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS];
            $add('late_production', 'Termini geçen iş emri', ProductionOrder::query()->whereIn('status', $open)->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today())->count(), '/atelier/production-orders', 'danger', 'clock');
        }
        if ($user->can('atelier.pattern.view')) {
            $add('conversions', 'İncelemede sayısallaştırma işi', ConversionJob::query()->where('status', ConversionJob::STATUS_NEEDS_REVIEW)->count(), '/atelier/conversions?status=needs_review', 'info', 'file');
            $add('patterns_work', 'Çıkarımı başarısız / elle izlenecek kalıp', Pattern::query()->whereIn('extraction_status', [Pattern::EXTRACTION_FAILED, Pattern::EXTRACTION_NEEDS_TRACING])->count(), '/atelier/patterns', 'warning', 'layers');
        }

        return $items;
    }
}
