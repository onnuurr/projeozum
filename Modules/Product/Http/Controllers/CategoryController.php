<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreCategoryRequest;
use Modules\Product\Http\Requests\UpdateCategoryRequest;
use Modules\Product\Models\Category;
use Modules\Product\Models\CategoryMarketplaceMapping;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Services\VatRateService;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->with(['parent:id,name', 'marketplaceMappings', 'productAttributes:id'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $c) use ($marketplaces) {
                $mpByKey = [];
                foreach ($marketplaces as $mp) {
                    $mapping = $c->marketplaceMappings->firstWhere('marketplace_id', $mp->id);
                    $mpByKey[$mp->key] = $mapping
                        ? [
                            'mapped'         => true,
                            'categoryPath'   => $mapping->category_path,
                            'externalId'     => $mapping->external_id,
                            'syncedProducts' => $mapping->synced_products,
                            'lastSync'       => optional($mapping->last_synced_at)?->diffForHumans() ?? '—',
                        ]
                        : ['mapped' => false];
                }

                return [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'slug'         => $c->slug,
                    'parent'       => $c->parent?->name ?? '—',
                    'parent_id'    => $c->parent_id,
                    'icon'         => $c->icon ?? 'package',
                    'vat_rate'     => $c->vat_rate !== null ? (float) $c->vat_rate : null,
                    'sort_order'   => $c->sort_order,
                    'productCount' => 0,
                    'status'       => $c->status,
                    'manuallyPassive' => $c->manually_passive,
                    'updatedAt'    => optional($c->updated_at)->format('Y-m-d'),
                    'marketplaces' => $mpByKey,
                    // Kategorinin kendi özellik seçimi; üst kategoriden gelenler istemcide ağaçtan hesaplanır
                    'productAttributes' => $c->productAttributes
                        ->map(fn (ProductAttribute $a) => ['id' => $a->id, 'required' => (bool) $a->pivot->required])
                        ->values(),
                ];
            });

        return Inertia::render('Product::Categories', [
            'categories'   => $categories,
            'defaultVatRate' => app(VatRateService::class)->defaultRate(),
            'productAttributes' => ProductAttribute::query()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'code', 'name', 'type'])
                ->map(fn (ProductAttribute $a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type]),
            'marketplaces' => $marketplaces->map(fn (Marketplace $m) => [
                'id'        => $m->id,
                'key'       => $m->key,
                'name'      => $m->name,
                'logoText'  => $m->logo_text,
                'color'     => $m->color,
                'connected' => $m->connected,
            ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Sıra boş bırakıldıysa kardeşlerin sonuna (formda da önceden doldurulur; elle değiştirilebilir)
        $data['sort_order'] ??= Category::nextSortOrder($data['parent_id'] ?? null);

        Category::create($this->withManualStatus($data));

        // Flash basılmıyor — Categories.vue onSuccess'te kendi toast'unu gösteriyor.
        return redirect()->route('products.categories.index');
    }

    /**
     * Ürün formundaki kategori arama kutusundan tek isimle hızlı ekleme.
     * Aynı isimde kategori zaten varsa yenisini oluşturmadan onu döner.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $name = trim($data['name']);

        $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $category) {
            $baseSlug = Str::slug($name) ?: 'kategori';
            $slug = $baseSlug;
            for ($suffix = 2; Category::where('slug', $slug)->exists(); $suffix++) {
                $slug = "{$baseSlug}-{$suffix}";
            }

            $category = Category::create([
                'name'       => $name,
                'slug'       => $slug,
                'icon'       => 'package',
                'status'     => 'active',
                'sort_order' => Category::nextSortOrder(null),
            ]);
        }

        return response()->json([
            'id'    => $category->id,
            'slug'  => $category->slug,
            'name'  => $category->name,
            'label' => $category->name,
            'icon'  => $category->icon ?? 'package',
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            return back()->withErrors(['parent_id' => __('product::messages.category_self_parent')]);
        }

        // Sıra boşaltıldıysa: başka üst kategoriye taşındıysa orada sona, değilse mevcut sıra korunur
        if (($data['sort_order'] ?? null) === null) {
            $newParent = $data['parent_id'] ?? null;
            $data['sort_order'] = (int) $newParent !== (int) $category->parent_id
                ? Category::nextSortOrder($newParent)
                : $category->sort_order;
        }

        $category->update($this->withManualStatus($data));

        return redirect()->route('products.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('products.categories.index');
    }

    /** Birden çok kategoriyi topluca siler. */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:product_categories,id'],
        ]);

        $count = 0;
        DB::transaction(function () use ($data, &$count) {
            foreach (Category::whereIn('id', $data['ids'])->get() as $category) {
                $category->delete();
                $count++;
            }
        });

        return redirect()->route('products.categories.index');
    }

    public function connectMarketplace(Marketplace $marketplace): RedirectResponse
    {
        $marketplace->update(['connected' => true]);

        return redirect()->route('products.categories.index');
    }

    public function storeMapping(Request $request, Category $category, Marketplace $marketplace): RedirectResponse
    {
        $data = $request->validate([
            'category_path' => ['required', 'string', 'max:255'],
            'external_id'   => ['nullable', 'string', 'max:64'],
        ]);

        CategoryMarketplaceMapping::updateOrCreate(
            ['category_id' => $category->id, 'marketplace_id' => $marketplace->id],
            [
                'category_path' => $data['category_path'],
                'external_id'   => $data['external_id'] ?? 'auto-' . random_int(1000, 9999),
                'last_synced_at' => now(),
            ],
        );

        return redirect()->route('products.categories.index');
    }

    public function destroyMapping(Category $category, Marketplace $marketplace): RedirectResponse
    {
        CategoryMarketplaceMapping::query()
            ->where('category_id', $category->id)
            ->where('marketplace_id', $marketplace->id)
            ->delete();

        return redirect()->route('products.categories.index');
    }

    /** Kategoriye yeni bir özellik tanımı ekler (Faz 3 — Attribute Engine). */
    /**
     * Formdaki durum seçimi kullanıcının niyetidir: "Pasif" → elle pasif (kalıcı), "Aktif" → otomatik.
     * Gerçek durumu kayıttan sonra CategoryStatusService belirler (yayında ürün yoksa pasif).
     */
    private function withManualStatus(array $data): array
    {
        if (array_key_exists('status', $data)) {
            $data['manually_passive'] = $data['status'] === 'passive';
        }

        return $data;
    }
}
