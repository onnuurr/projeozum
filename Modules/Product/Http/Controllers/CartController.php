<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;

class CartController extends Controller
{

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'color'      => ['nullable', 'string', 'max:32'],
            'size'       => ['nullable', 'string', 'max:16'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $qty = (int) ($data['qty'] ?? 1);

        // Tenant kullanıcı erişimi olmayan bir ürünü sepete eklemeye çalışıyorsa engelle.
        $user = $request->user();
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

        // Tenant fiyatlandırma için resolver service (kapsama dışıysa null kalır).
        $tenant = $tenantId !== null ? Tenant::with('type')->find($tenantId) : null;
        $accessService = app(TenantAccessService::class);

        try {
            $productName = DB::transaction(function () use ($request, $data, $qty, $tenant, $accessService) {
                $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
                $variant = isset($data['variant_id'])
                    ? ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($data['variant_id'])
                        ->lockForUpdate()
                        ->firstOrFail()
                    : $this->pickVariant($product->id, $data['color'] ?? null, $data['size'] ?? null, $qty);

                // Aynı varyanta tekrar eklenince yeni satır açmıyoruz — sadece adet artar.
                // Varyantsız ürünlerde (variant_id=null) color/size kombinasyonuna göre eşle.
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

                if ($variant) {
                    $this->decrementVariantStock($variant->id, $qty);
                }

                // Etiketler için kullanıcının seçtiğini değil, varyantın gerçek
                // değerlerini yazıyoruz — böylece grid'den (size=null) ve detaydan
                // (size="M") gelen iki istek aynı satıra düşer.
                $color = $variant?->color_name ?? ($data['color'] ?? null);
                $size  = $variant?->size       ?? ($data['size']  ?? null);

                // Tenant kullanıcıda fiyat: pivot custom > price_list > variant.price
                $unitPrice = $tenant
                    ? $accessService->priceFor($tenant, $product, $variant)
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
        } catch (ValidationException $e) {
            throw $e;
        }

        return back()->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => 'Sepete eklendi',
                'message' => $productName,
            ],
        ]);
    }

    public function updateQty(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $newQty = (int) $data['qty'];

        DB::transaction(function () use ($cartItem, $newQty) {
            $delta = $newQty - $cartItem->qty;

            if ($cartItem->variant_id) {
                if ($delta > 0) {
                    $this->decrementVariantStock($cartItem->variant_id, $delta);
                } elseif ($delta < 0) {
                    ProductVariant::query()
                        ->whereKey($cartItem->variant_id)
                        ->increment('stock', -$delta);
                }
            }

            $cartItem->qty = $newQty;
            $cartItem->save();
        });

        return back();
    }

    public function remove(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        DB::transaction(function () use ($cartItem) {
            if ($cartItem->variant_id) {
                ProductVariant::query()
                    ->whereKey($cartItem->variant_id)
                    ->increment('stock', $cartItem->qty);
            }
            $cartItem->delete();
        });

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $items = CartItem::query()
                ->where('user_id', $request->user()->id)
                ->get();

            foreach ($items as $item) {
                if ($item->variant_id) {
                    ProductVariant::query()
                        ->whereKey($item->variant_id)
                        ->increment('stock', $item->qty);
                }
            }

            CartItem::query()
                ->where('user_id', $request->user()->id)
                ->delete();
        });

        return back();
    }

    /**
     * İstenen color/size kombinasyonuna uyan, yeterli stoğu olan ilk varyantı seçer.
     * Eşleşme yoksa stoğu olan ilk varyantı döner; ürünün hiç varyantı yoksa null.
     */
    private function pickVariant(int $productId, ?string $color, ?string $size, int $qty): ?ProductVariant
    {
        $hasVariants = ProductVariant::query()->where('product_id', $productId)->exists();
        if (! $hasVariants) {
            return null;
        }

        $candidate = ProductVariant::query()
            ->where('product_id', $productId)
            ->when($color, fn ($q) => $q->where('color_name', $color))
            ->when($size, fn ($q) => $q->where('size', $size))
            ->where('stock', '>=', $qty)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $candidate) {
            // Color/size filtresi uymadıysa veya yeterli stok yoksa stoğu olan herhangi
            // bir varyantı dene; o da yoksa stok hatası ver.
            $candidate = ProductVariant::query()
                ->where('product_id', $productId)
                ->where('stock', '>=', $qty)
                ->orderByDesc('stock')
                ->lockForUpdate()
                ->first();
        }

        if (! $candidate) {
            throw ValidationException::withMessages([
                'qty' => 'Yeterli stok yok.',
            ]);
        }

        return $candidate;
    }

    private function decrementVariantStock(int $variantId, int $qty): void
    {
        $affected = ProductVariant::query()
            ->whereKey($variantId)
            ->where('stock', '>=', $qty)
            ->update(['stock' => DB::raw("stock - {$qty}")]);

        if ($affected === 0) {
            throw ValidationException::withMessages([
                'qty' => 'Stok yetersiz.',
            ]);
        }
    }
}
