<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Category;
use Modules\Product\Models\ProductAttribute;

/**
 * Ürün Ayarları → Özellikler yazma işlemleri ve kategorilere özellik ataması.
 *
 * Kod ve tip oluşturulduktan sonra değişmez (Bagisto attribute'unun anahtarı ve değer saklama
 * biçimi). Seçenekler formdaki sırayla bütün liste olarak gelir: id'li olan güncellenir (ad
 * değişirse Bagisto'da yerinde yeniden adlandırılır), id'siz olan eklenir, listede olmayan silinir.
 * Ürünlerde kullanılan özellik/seçenek silinemez — ürün değerleri boşa düşerdi.
 *
 * Model olayları (created/updated/deleted) mağaza senkronunu tetikler (BagistoServiceProvider);
 * bu yüzden silmeler de tek tek model üzerinden yapılır.
 */
class ProductAttributeAdminService
{
    public function __construct(private readonly ProductAttributeService $attributes) {}

    /** @param  array{code: string, name: string, type: string, is_filterable?: bool, sort_order?: int|null, options?: list<array{id?: int|null, name: string}>}  $data */
    public function create(array $data): ProductAttribute
    {
        return DB::transaction(function () use ($data) {
            $attribute = ProductAttribute::create([
                'code'          => $data['code'],
                'name'          => $data['name'],
                'type'          => $data['type'],
                'is_filterable' => in_array($data['type'], ProductAttribute::FILTERABLE_TYPES, true) && ($data['is_filterable'] ?? true),
                'sort_order'    => $data['sort_order'] ?? ((int) ProductAttribute::max('sort_order') + 1),
            ]);

            $this->syncOptions($attribute, $data['options'] ?? []);

            return $attribute;
        });
    }

    /** @param  array{name: string, is_filterable?: bool, sort_order?: int|null, options?: list<array{id?: int|null, name: string}>}  $data */
    public function update(ProductAttribute $attribute, array $data): ProductAttribute
    {
        return DB::transaction(function () use ($attribute, $data) {
            $attribute->update([
                'name'          => $data['name'],
                'is_filterable' => in_array($attribute->type, ProductAttribute::FILTERABLE_TYPES, true) && ($data['is_filterable'] ?? $attribute->is_filterable),
                'sort_order'    => $data['sort_order'] ?? $attribute->sort_order,
            ]);

            $this->syncOptions($attribute, $data['options'] ?? []);

            return $attribute;
        });
    }

    public function delete(ProductAttribute $attribute): void
    {
        $this->bulkDelete([$attribute->id]);
    }

    /** @param  array<int, int>  $ids */
    public function bulkDelete(array $ids): int
    {
        $attributes = ProductAttribute::whereIn('id', $ids)->get();

        $system = $attributes->filter(fn (ProductAttribute $a) => $a->isSystem());
        if ($system->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attribute' => __('product::messages.attribute_system', ['names' => $system->pluck('name')->implode(', ')]),
            ]);
        }

        $usage = $this->attributes->usage()['attributes'];

        $used = $attributes->filter(fn (ProductAttribute $a) => ($usage[$a->code] ?? 0) > 0);
        if ($used->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attribute' => __('product::messages.attribute_in_use', [
                    'names' => $used->pluck('name')->implode(', '),
                ]),
            ]);
        }

        return DB::transaction(function () use ($attributes) {
            // Seçenekler cascade ile gider; Bagisto'da attribute silinince seçenekleri de silinir.
            $attributes->each->delete();

            return $attributes->count();
        });
    }

    /**
     * Kategorinin kendi özellik seçimini bütün liste olarak yazar (üst kategoriden gelenler
     * burada değil, üst kategoride yönetilir).
     *
     * @param  list<array{id: int, required?: bool}>  $items
     */
    public function syncCategory(Category $category, array $items): void
    {
        $category->productAttributes()->sync(
            collect($items)->mapWithKeys(fn (array $i) => [(int) $i['id'] => ['required' => (bool) ($i['required'] ?? false)]])->all(),
        );

        $this->attributes->flush();
    }

    /** @param  list<array{id?: int|null, name: string, icon?: string|null}>  $options */
    private function syncOptions(ProductAttribute $attribute, array $options): void
    {
        if (! $attribute->hasOptions()) {
            return;
        }

        // KDV seçeneğinin adı orandır (VatRateService::parseRate); Bagisto'da vergi kategorisi olmayan
        // oran mağazada ürünü vergisiz bırakırdı.
        if ($attribute->code === ProductAttribute::VAT_CODE) {
            $invalid = collect($options)->pluck('name')
                ->filter(fn ($n) => ! in_array(VatRateService::parseRate($n), VatRateService::ALLOWED_RATES, true));
            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'options' => __('product::messages.vat_option_invalid', ['names' => $invalid->implode(', ')]),
                ]);
            }
        }

        $existing = $attribute->options()->get()->keyBy('id');
        $keptIds = collect($options)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $removed = $existing->except($keptIds);
        if ($removed->isNotEmpty()) {
            $usage = $this->attributes->usage()['options'];
            $used = $removed->filter(fn ($o) => ($usage[$o->id] ?? 0) > 0);
            if ($used->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'options' => __('product::messages.attribute_option_in_use', [
                        'names' => $used->pluck('name')->implode(', '),
                    ]),
                ]);
            }
            $removed->each->delete();
        }

        foreach (array_values($options) as $i => $option) {
            $values = ['name' => $option['name'], 'icon' => $option['icon'] ?? null, 'sort_order' => $i + 1];
            $model = isset($option['id']) ? $existing->get((int) $option['id']) : null;

            $model ? $model->update($values) : $attribute->options()->create($values);
        }

        $this->attributes->flush();
    }
}
