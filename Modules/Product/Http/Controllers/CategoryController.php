<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Category;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
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
            ]);

        return Inertia::render('Product::Categories', [
            'categories' => $categories,
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

        return redirect()->route('products.categories.index')
            ->with('success', "{$count} kategori silindi.");
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
