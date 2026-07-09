<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Product\Http\Requests\StoreCartItemRequest;
use Modules\Product\Http\Requests\UpdateCartItemQtyRequest;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\StockService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;

/**
 * Sepet, stoğa ASLA dokunmaz (D1). Adet/varyant seçimi yalnızca YUMUŞAK bir UX
 * kontrolüdür (StockService::availableForVariant); sert stok düşümü sipariş anında
 * CheckoutService::place() → StockService::decrementForOrder ile yapılır.
 */
class CartController extends Controller
{
    public function __construct(
        private StockService $stock,
        private TenantAccessService $access,
    ) {}

    public function add(StoreCartItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $qty  = (int) ($data['qty'] ?? 1);

        // Tenant kullanıcı erişimi olmayan bir ürünü sepete eklemeye çalışıyorsa engelle.
        $user     = $request->user();
        $tenantId = (! $user->isSuperadmin() && $user->tenant_id) ? $user->tenant_id : null;
        if ($tenantId !== null) {
            $accessible = Product::query()
                ->accessibleToTenant($tenantId)
                ->whereKey($data['product_id'])
                ->exists();

            if (! $accessible) {
                throw ValidationException::withMessages([
                    'product_id' => 'Bu ürünü görme yetkiniz yok.',
                ]);
            }
        }

        $tenant = $tenantId !== null ? Tenant::with('type')->find($tenantId) : null;

        $productName = DB::transaction(function () use ($request, $data, $qty, $tenant) {
            $product = Product::query()->findOrFail($data['product_id']);
            $variant = isset($data['variant_id'])
                ? ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey($data['variant_id'])
                    ->firstOrFail()
                : $this->pickVariant($product->id, $data['color'] ?? null, $data['size'] ?? null, $qty);

            // Aynı varyanta tekrar eklenince yeni satır açmıyoruz — sadece adet artar.
            $itemQuery = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->where('product_id', $product->id);

            if ($variant) {
                $itemQuery->where('variant_id', $variant->id);
            } else {
                $itemQuery
                    ->whereNull('variant_id')
                    ->where('color', $data['color'] ?? null)
                    ->where('size', $data['size'] ?? null);
            }

            $item = $itemQuery->lockForUpdate()->first();

            $newQty = ($item?->qty ?? 0) + $qty;
            if ($newQty > 99) {
                throw ValidationException::withMessages([
                    'qty' => 'Bir üründen en fazla 99 adet eklenebilir.',
                ]);
            }

            // Etiketler için kullanıcının seçtiğini değil, varyantın gerçek değerlerini yazıyoruz.
            $color = $variant?->color_name ?? ($data['color'] ?? null);
            $size  = $variant?->size       ?? ($data['size']  ?? null);

            // Görüntü fiyatı: pivot custom > price_list > variant.price. Sipariş anında yeniden çözülür.
            $unitPrice = $tenant
                ? $this->access->priceFor($tenant, $product, $variant)
                : (float) ($variant?->price ?? $product->price);

            if ($item) {
                $item->qty   = $newQty;
                $item->color = $color;
                $item->size  = $size;
                $item->price = $unitPrice;
                $item->save();
            } else {
                CartItem::create([
                    'user_id'    => $request->user()->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'color'      => $color,
                    'size'       => $size,
                    'qty'        => $qty,
                    'price'      => $unitPrice,
                ]);
            }

            return $product->name;
        });

        return back()->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => 'Sepete eklendi',
                'message' => $productName,
            ],
        ]);
    }

    public function updateQty(UpdateCartItemQtyRequest $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $cartItem->qty = (int) $request->validated()['qty'];
        $cartItem->save();

        return back();
    }

    public function remove(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $cartItem->delete();

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        CartItem::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return back();
    }

    /**
     * İstenen color/size kombinasyonuna uyan varyantı seçer; öncelikle satılabilir
     * stoğu yeterli olanı tercih eder (YUMUŞAK — bloklamaz; sert kontrol place()'te).
     * Ürünün hiç varyantı yoksa null döner.
     */
    private function pickVariant(int $productId, ?string $color, ?string $size, int $qty): ?ProductVariant
    {
        $variants = ProductVariant::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $matched = $variants->filter(function (ProductVariant $v) use ($color, $size) {
            return (! $color || $v->color_name === $color)
                && (! $size || $v->size === $size);
        });

        $pool = $matched->isNotEmpty() ? $matched : $variants;

        foreach ($pool as $variant) {
            if ($this->stock->availableForVariant($variant->id) >= $qty) {
                return $variant;
            }
        }

        // Yeterli stoklu varyant yok — yine de ekle (place() sert kontrol yapar).
        return $pool->first();
    }
}
