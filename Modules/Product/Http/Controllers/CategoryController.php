<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Category;
use Modules\Product\Models\CategoryAttributeDefinition;
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
            ->with(['parent:id,name', 'marketplaceMappings', 'attributeDefinitions'])
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
                    'attributeDefinitions' => $c->attributeDefinitions->map(fn (CategoryAttributeDefinition $d) => [
                        'id'         => $d->id,
                        'key'        => $d->key,
                        'label'      => $d->label,
                        'type'       => $d->type,
                        'options'    => $d->options ?? [],
                        'required'   => $d->required,
                        'sort_order' => $d->sort_order,
                    ]),
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
                'icon'       => '📦',
                'status'     => 'active',
                'sort_order' => (int) Category::max('sort_order') + 1,
            ]);
        }

        return response()->json([
            'id'    => $category->id,
            'slug'  => $category->slug,
            'name'  => $category->name,
            'label' => $category->name,
            'icon'  => $category->icon ?? '📦',
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category->id);

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            return back()->withErrors(['parent_id' => 'Bir kategori kendisinin üstü olamaz.']);
        }

        $category->update($data);

        return redirect()->route('products.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('products.categories.index');
    }

    /**
     * Birden çok kategoriyi topluca siler.
     */
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

    /**
     * Kategoriye yeni bir özellik tanımı ekler (Faz 3 — Attribute Engine).
     */
    public function storeAttributeDefinition(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateAttributeDefinition($request, $category->id);

        $category->attributeDefinitions()->create($data);

        return redirect()->route('products.categories.index');
    }

    public function updateAttributeDefinition(Request $request, Category $category, CategoryAttributeDefinition $definition): RedirectResponse
    {
        $data = $this->validateAttributeDefinition($request, $category->id, $definition->id);

        $definition->update($data);

        return redirect()->route('products.categories.index');
    }

    public function destroyAttributeDefinition(Category $category, CategoryAttributeDefinition $definition): RedirectResponse
    {
        $definition->delete();

        return redirect()->route('products.categories.index');
    }

    private function validateAttributeDefinition(Request $request, int $categoryId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'key'        => [
                'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('category_attribute_definitions', 'key')
                    ->where('category_id', $categoryId)
                    ->ignore($ignoreId),
            ],
            'label'      => ['required', 'string', 'max:120'],
            'type'       => ['required', Rule::in(['string', 'enum', 'number', 'boolean'])],
            'options'    => ['nullable', 'array'],
            'options.*'  => ['string', 'max:120'],
            'required'   => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'key.regex' => 'Anahtar küçük harfle başlamalı, yalnızca küçük harf/rakam/alt çizgi içerebilir.',
        ]);

        $data['category_id'] = $categoryId;
        $data['required']    = $data['required'] ?? false;
        $data['options']     = $data['type'] === 'enum' ? ($data['options'] ?? []) : null;

        return $data;
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
