<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Modules\Product\Models\CategoryAttributeDefinition;

/**
 * Kategoriye göre değişen ürün özelliklerinin (Faz 3) tanım okuma, doğrulama
 * kuralı üretme ve kayıt öncesi whitelist kesişimi. Atelier'in
 * `MaterialSpecService` "validate + service-level whitelist" ikili savunma
 * deseniyle aynı disiplin — tek fark anahtarların sabit değil, kategoriye
 * göre DB'den dinamik gelmesi.
 */
class ProductAttributeService
{
    /**
     * @return Collection<int, CategoryAttributeDefinition>
     */
    public function definitionsFor(?int $categoryId): Collection
    {
        if ($categoryId === null) {
            return new Collection();
        }

        return CategoryAttributeDefinition::query()
            ->where('category_id', $categoryId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * `attributes.<key>` için StoreProductRequest/UpdateProductRequest'in
     * birleştireceği dinamik doğrulama kuralları.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(?int $categoryId): array
    {
        $rules = [];

        foreach ($this->definitionsFor($categoryId) as $definition) {
            $field = "attributes.{$definition->key}";
            $typeRule = match ($definition->type) {
                'enum'    => Rule::in($definition->options ?? []),
                'number'  => 'numeric',
                'boolean' => 'boolean',
                default   => ['string', 'max:255'],
            };

            $rules[$field] = [
                $definition->required ? 'required' : 'nullable',
                ...(is_array($typeRule) ? $typeRule : [$typeRule]),
            ];
        }

        return $rules;
    }

    /**
     * Kaydetmeden hemen önceki son whitelist kesişimi — kategori tanımında
     * olmayan (silinmiş tanım, kategori değişmiş vb.) anahtarlar sessizce
     * elenir, hata fırlatmaz.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filterToDefinedKeys(?int $categoryId, array $input): array
    {
        $keys = $this->definitionsFor($categoryId)->pluck('key')->all();

        return Arr::only($input, $keys);
    }
}
