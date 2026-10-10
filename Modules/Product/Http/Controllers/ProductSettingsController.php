<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ModuleSettingsRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Product\Services\ProductAttributeService;
use Modules\Product\Services\VariantOptionService;

/**
 * Product modülünün tek ayar sayfası (product-settings.view). Her ayar bir sekmedir ve
 * kendi yetkisiyle ayrıca kilitlidir: kullanıcının görme yetkisi olmayan sekmenin verisi
 * hiç gönderilmez (prop null). Hiçbir sekmeyi göremeyen kullanıcıya sayfa yokmuş gibi 403
 * döner (çark da gizlenir, bkz. ModuleSettingsRegistry::canAccess). Yeni bir Product ayarı
 * eklenirken buraya yeni sekme, ProductServiceProvider'daki tabPermissions'a da yetkisi eklenir.
 */
class ProductSettingsController extends Controller
{
    public function index(Request $request, ModuleSettingsRegistry $settings): Response
    {
        $user = $request->user();
        abort_unless($settings->canAccess($user, 'product'), 403);

        $canVariantOptions = $user->can('variant-option.view');

        return Inertia::render('Product::Settings', [
            'tab'        => $request->query('tab'),
            'sizes'      => $canVariantOptions ? $this->sizes() : null,
            'colors'     => $canVariantOptions ? $this->colors() : null,
            'attributes' => $user->can('product-attribute.view') ? $this->attributes() : null,
        ]);
    }

    private function attributes(): array
    {
        $usage = app(ProductAttributeService::class)->usage();

        return ProductAttribute::query()
            ->with('options')
            ->withCount('categories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductAttribute $a) => [
                'id'            => $a->id,
                'code'          => $a->code,
                'name'          => $a->name,
                'type'          => $a->type,
                'is_filterable' => $a->is_filterable,
                'sort_order'    => $a->sort_order,
                'categoryCount' => $a->categories_count,
                'productCount'  => $usage['attributes'][$a->code] ?? 0,
                'options'       => $a->options->map(fn ($o) => [
                    'id'           => $o->id,
                    'name'         => $o->name,
                    'icon'         => $o->icon,
                    'productCount' => $usage['options'][$o->id] ?? 0,
                ])->all(),
            ])
            ->all();
    }

    private function sizes(): array
    {
        $usage = app(VariantOptionService::class)->variantUsage('size');

        return ProductSize::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductSize $s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'sort_order'   => $s->sort_order,
                'variantCount' => (int) ($usage[$s->name] ?? 0),
            ])
            ->all();
    }

    private function colors(): array
    {
        $usage = app(VariantOptionService::class)->variantUsage('color_name');

        return ProductColor::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductColor $c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'hex'          => $c->hex,
                'sort_order'   => $c->sort_order,
                'variantCount' => (int) ($usage[$c->name] ?? 0),
            ])
            ->all();
    }
}
