<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Product\Models\CartItem;
use Modules\Superadmin\Services\MenuTreeBuilder;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user'        => $user,
                'permissions' => fn () => $user?->getAllPermissions()?->pluck('name')->all() ?? [],
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'cart'  => fn () => $this->cartPayload($user?->id),
            'menu'  => fn () => MenuTreeBuilder::forUser($user),
            'flash' => fn () => [
                'toast' => $this->resolveToast($request),
            ],
        ];
    }

    /**
     * Normalize backend flash into a single toast shape or null.
     * Supports Schema A: ->with('success'|'error'|'warning'|'info', 'text')
     * and Schema B: ->with('flash', ['toast' => ['type', 'title', 'message']]).
     */
    private function resolveToast(Request $request): ?array
    {
        // Schema B: structured flash.toast
        $flashBag = $request->session()->get('flash');
        if (is_array($flashBag) && isset($flashBag['toast']) && is_array($flashBag['toast'])) {
            $t = $flashBag['toast'];
            return [
                'type'    => $t['type']    ?? 'info',
                'title'   => $t['title']   ?? null,
                'message' => $t['message'] ?? '',
            ];
        }

        // Schema A: simple string keys
        foreach (['success', 'error', 'warning', 'info'] as $type) {
            $msg = $request->session()->get($type);
            if (is_string($msg) && $msg !== '') {
                return ['type' => $type, 'title' => null, 'message' => $msg];
            }
        }

        return null;
    }

    /**
     * Build the cart payload shared with every Inertia response.
     *
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function cartPayload(?int $userId): array
    {
        if (! $userId) {
            return ['items' => []];
        }

        $items = CartItem::query()
            ->with([
                'product:id,name,slug,brand_id',
                'product.brand:id,name',
                'variant:id,stock',
            ])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(function (CartItem $i) {
                // variant.stock zaten "sepete eklendikten sonra kalan" değer.
                // Bu yüzden maxQty = sepetteki adet + kalan stok.
                $remaining = (int) ($i->variant?->stock ?? 0);
                $maxQty    = $i->variant_id ? ($i->qty + $remaining) : 99;
                return [
                    'id'          => $i->id,
                    'key'         => $i->id,
                    'productId'   => $i->product_id,
                    'productSlug' => $i->product?->slug,
                    'variantId'   => $i->variant_id,
                    'name'        => $i->product?->name ?? 'Ürün',
                    'brand'       => $i->product?->brand?->name ?? '',
                    'image'       => "https://picsum.photos/seed/tek-p{$i->product_id}/200/250",
                    'color'       => $i->color,
                    'size'        => $i->size,
                    'qty'         => (int) $i->qty,
                    'maxQty'      => min(99, $maxQty),
                    'price'       => (float) $i->price,
                ];
            })
            ->values()
            ->all();

        return ['items' => $items];
    }
}
