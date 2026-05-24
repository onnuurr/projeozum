<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Category;
use Modules\Product\Models\CategoryMarketplaceMapping;
use Modules\Product\Models\Marketplace;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->with(['parent:id,name', 'marketplaceMappings'])
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
                    'icon'         => $c->icon ?? '📦',
                    'sort_order'   => $c->sort_order,
                    'productCount' => 0,
                    'status'       => $c->status,
                    'updatedAt'    => optional($c->updated_at)->format('Y-m-d'),
                    'marketplaces' => $mpByKey,
                ];
            });

        return Inertia::render('Product::Categories', [
            'categories'   => $categories,
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

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validateCategory($request));

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori eklendi.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category->id);

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            return back()->withErrors(['parent_id' => 'Bir kategori kendisinin üstü olamaz.']);
        }

        $category->update($data);

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori silindi.');
    }

    public function connectMarketplace(Marketplace $marketplace): RedirectResponse
    {
        $marketplace->update(['connected' => true]);

        return redirect()->route('products.categories.index')
            ->with('success', "{$marketplace->name} bağlandı.");
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

        return redirect()->route('products.categories.index')
            ->with('success', "{$category->name} → {$marketplace->name} eşleştirildi.");
    }

    public function destroyMapping(Category $category, Marketplace $marketplace): RedirectResponse
    {
        CategoryMarketplaceMapping::query()
            ->where('category_id', $category->id)
            ->where('marketplace_id', $marketplace->id)
            ->delete();

        return redirect()->route('products.categories.index')
            ->with('success', "{$category->name} → {$marketplace->name} eşleştirmesi kaldırıldı.");
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:191'],
            'slug'       => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_categories', 'slug')->ignore($ignoreId),
            ],
            'icon'       => ['nullable', 'string', 'max:16'],
            'parent_id'  => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'status'     => ['required', Rule::in(['active', 'passive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        ]);
    }
}
