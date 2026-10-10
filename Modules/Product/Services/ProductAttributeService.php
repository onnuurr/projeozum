<?php

namespace Modules\Product\Services;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;

/**
 * Ürün kaydı tarafı: bir kategoride hangi global özelliklerin geçerli olduğu (kategori + üst
 * kategorilerinin seçimi), ürün formundaki dinamik doğrulama kuralları ve kayıt öncesi whitelist +
 * tip normalizasyonu. Değerler products.attributes[code]'da tutulur:
 *   select → seçenek id'si (int), multiselect/checkbox → id dizisi, text/textarea → string,
 *   number/price → sayı, boolean → bool, date → Y-m-d, datetime → Y-m-d H:i:s,
 *   image/file → Media diskinde product-attributes/… relative path.
 * Atelier'in MaterialSpecService "validate + service-level whitelist" ikili savunmasıyla aynı disiplin.
 */
class ProductAttributeService
{
    /** @var array<int, list<array{attribute: ProductAttribute, required: bool}>>|null */
    private ?array $byCategory = null;

    /**
     * Kategori id → geçerli özellikler (kategorinin kendi seçimi + üst kategorilerinden miras;
     * aynı özellik birden çok seviyede seçiliyse herhangi birinde zorunluysa zorunlu).
     *
     * @return array<int, list<array{attribute: ProductAttribute, required: bool}>>
     */
    public function byCategory(): array
    {
        if ($this->byCategory !== null) {
            return $this->byCategory;
        }

        $attributes = ProductAttribute::with('options')->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $own = [];
        foreach (Category::query()->with('productAttributes:id')->get() as $category) {
            foreach ($category->productAttributes as $attribute) {
                $own[$category->id][$attribute->id] = (bool) $attribute->pivot->required;
            }
        }

        $result = [];
        foreach (array_keys($parents) as $categoryId) {
            $required = [];
            $seen = [];
            for ($id = $categoryId; $id !== null && ! isset($seen[$id]); $id = $parents[$id] ?? null) {
                $seen[$id] = true;
                foreach ($own[$id] ?? [] as $attributeId => $isRequired) {
                    $required[$attributeId] = ($required[$attributeId] ?? false) || $isRequired;
                }
            }

            // Sistem özellikleri (KDV) kategori ataması olmadan her kategoride geçerli.
            $result[$categoryId] = $attributes
                ->filter(fn (ProductAttribute $a) => $a->isSystem() || array_key_exists($a->id, $required))
                ->map(fn (ProductAttribute $a) => ['attribute' => $a, 'required' => $required[$a->id] ?? false])
                ->values()
                ->all();
        }

        return $this->byCategory = $result;
    }

    /** @return list<array{attribute: ProductAttribute, required: bool}> */
    public function definitionsFor(?int $categoryId): array
    {
        return $categoryId === null ? [] : ($this->byCategory()[$categoryId] ?? []);
    }

    /**
     * Ürün formu için: kategori id → [{code, name, type, required, options: [{value, label}]}].
     * Sistem özellikleri (KDV) burada yok — formda kendi alanlarında gösterilir.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function formDefinitions(): array
    {
        return collect($this->byCategory())
            ->map(fn (array $defs) => array_values(array_filter($defs, fn (array $d) => ! $d['attribute']->isSystem())))
            ->filter()
            ->map(fn (array $defs) => array_map(fn (array $d) => [
                'code'     => $d['attribute']->code,
                'name'     => $d['attribute']->name,
                'type'     => $d['attribute']->type,
                'required' => $d['required'],
                'options'  => $d['attribute']->options->map(fn ($o) => ['value' => $o->id, 'label' => $o->name, 'icon' => $o->icon])->all(),
            ], $defs))
            ->all();
    }

    /**
     * `attributes.<code>` için StoreProductRequest/UpdateProductRequest'in birleştireceği kurallar.
     * Görsel/dosya: yeni yükleme (UploadedFile) ya da mevcut değer (kendi klasöründeki path).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(?int $categoryId): array
    {
        $rules = [];

        foreach ($this->definitionsFor($categoryId) as ['attribute' => $attribute, 'required' => $required]) {
            $field = "attributes.{$attribute->code}";
            $presence = $required ? 'required' : 'nullable';
            $optionIds = $attribute->options->pluck('id')->all();

            match ($attribute->type) {
                ProductAttribute::TYPE_SELECT => $rules[$field] = [$presence, 'integer', Rule::in($optionIds)],
                ProductAttribute::TYPE_MULTISELECT, ProductAttribute::TYPE_CHECKBOX => [
                    $rules[$field] = [$presence, 'array'],
                    $rules["{$field}.*"] = ['integer', Rule::in($optionIds)],
                ],
                ProductAttribute::TYPE_BOOLEAN => $rules[$field] = ['nullable', 'boolean'],
                ProductAttribute::TYPE_TEXTAREA => $rules[$field] = [$presence, 'string', 'max:5000'],
                ProductAttribute::TYPE_NUMBER => $rules[$field] = [$presence, 'numeric', 'between:-999999999,999999999'],
                ProductAttribute::TYPE_PRICE => $rules[$field] = [$presence, 'numeric', 'min:0', 'max:999999999'],
                ProductAttribute::TYPE_DATE => $rules[$field] = [$presence, 'date_format:Y-m-d'],
                ProductAttribute::TYPE_DATETIME => $rules[$field] = [$presence, 'date'],
                ProductAttribute::TYPE_IMAGE => $rules[$field] = [$presence, $this->uploadRule(['jpeg', 'jpg', 'png', 'webp'], 5120)],
                ProductAttribute::TYPE_FILE => $rules[$field] = [$presence, $this->uploadRule(['pdf', 'jpeg', 'jpg', 'png', 'webp'], 10240)],
                default => $rules[$field] = [$presence, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * Görsel/dosya alanı: yeni yükleme uzantı/boyut kurallarına uymalı; string ise yalnızca bu
     * özellik klasöründeki bir path olabilir (başka bir depolama yoluna işaret edilemesin).
     * SVG/HTML bilerek yok — aynı origin'den servis edilince script çalıştırabilir.
     *
     * @param  list<string>  $extensions
     */
    private function uploadRule(array $extensions, int $maxKb): \Closure
    {
        return function (string $field, mixed $value, \Closure $fail) use ($extensions, $maxKb): void {
            if ($value instanceof UploadedFile) {
                $ext = strtolower($value->getClientOriginalExtension());
                $guessed = strtolower((string) $value->guessExtension());
                if (! in_array($ext, $extensions, true) || ! in_array($guessed === 'jpeg' ? 'jpg' : $guessed, array_map(fn ($e) => $e === 'jpeg' ? 'jpg' : $e, $extensions), true)) {
                    $fail(__('product::messages.validation.attribute_upload_type', ['types' => strtoupper(implode(', ', array_unique($extensions)))]));
                } elseif ($value->getSize() > $maxKb * 1024) {
                    $fail(__('product::messages.validation.attribute_upload_size', ['mb' => $maxKb / 1024]));
                }

                return;
            }

            if (! is_string($value) || ! preg_match('#^'.ProductAttribute::UPLOAD_DIR.'/[A-Za-z0-9._-]+$#', $value)) {
                $fail(__('product::messages.validation.attribute_upload_type', ['types' => strtoupper(implode(', ', array_unique($extensions)))]));
            }
        };
    }

    /**
     * Ürün kaydından önce: görsel/dosya alanlarındaki yeni yüklemeleri Media diskine yazar,
     * yerine relative path koyar. Diğer değerlere dokunmaz.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function storeUploads(?int $categoryId, array $input): array
    {
        foreach ($this->definitionsFor($categoryId) as ['attribute' => $attribute]) {
            $value = $input[$attribute->code] ?? null;
            if ($attribute->isUpload() && $value instanceof UploadedFile) {
                $input[$attribute->code] = $value->store(ProductAttribute::UPLOAD_DIR, Media::disk());
            }
        }

        return $input;
    }

    /**
     * Ürün güncellemesinden sonra: eski değerlerde olup yenilerde kalmayan özellik dosyalarını siler
     * (değiştirilen/kaldırılan görsel, kategorisi değişen ürün).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function deleteReplacedUploads(array $old, array $new): void
    {
        $prefix = ProductAttribute::UPLOAD_DIR.'/';
        $kept = array_filter($new, fn ($v) => is_string($v) && str_starts_with($v, $prefix));

        foreach ($old as $value) {
            if (is_string($value) && str_starts_with($value, $prefix) && ! in_array($value, $kept, true)) {
                Storage::disk(Media::disk())->delete($value);
            }
        }
    }

    /**
     * Kaydetmeden hemen önceki whitelist + tip normalizasyonu: kategoride geçerli olmayan
     * (kategori değişmiş, özellik kaldırılmış) anahtarlar ve boş değerler sessizce elenir.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(?int $categoryId, array $input): array
    {
        $values = [];

        foreach ($this->definitionsFor($categoryId) as ['attribute' => $attribute]) {
            $raw = $input[$attribute->code] ?? null;
            $optionIds = $attribute->options->pluck('id')->all();
            $text = is_scalar($raw) && trim((string) $raw) !== '' ? trim((string) $raw) : null;

            $value = match ($attribute->type) {
                ProductAttribute::TYPE_SELECT => in_array((int) $raw, $optionIds, true) ? (int) $raw : null,
                ProductAttribute::TYPE_MULTISELECT, ProductAttribute::TYPE_CHECKBOX => array_values(array_intersect($optionIds, array_map('intval', (array) $raw))) ?: null,
                ProductAttribute::TYPE_BOOLEAN => $raw === null || $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                ProductAttribute::TYPE_NUMBER => is_numeric($text) ? $text + 0 : null,
                ProductAttribute::TYPE_PRICE => is_numeric($text) ? round((float) $text, 2) : null,
                ProductAttribute::TYPE_DATE => $text ? Carbon::parse($text)->format('Y-m-d') : null,
                ProductAttribute::TYPE_DATETIME => $text ? Carbon::parse($text)->format('Y-m-d H:i:s') : null,
                ProductAttribute::TYPE_IMAGE, ProductAttribute::TYPE_FILE => $text && str_starts_with($text, ProductAttribute::UPLOAD_DIR.'/') ? $text : null,
                default => $text,
            };

            if ($value !== null) {
                $values[$attribute->code] = $value;
            }
        }

        return $values;
    }

    /**
     * Ürünün değerlerini koda göre okunur hale getirir (seçenek id → ad, dosya → tam URL). Mağaza
     * senkronu ve ürün kartı gibi tüketiciler için: select → ad, multiselect/checkbox → ad listesi.
     *
     * @return array<string, mixed>
     */
    public function labelledValues(Product $product): array
    {
        $values = [];

        foreach ($this->definitionsFor($product->category_id) as ['attribute' => $attribute]) {
            $raw = ($product->attributes ?? [])[$attribute->code] ?? null;
            $names = $attribute->options->pluck('name', 'id');

            $value = match ($attribute->type) {
                ProductAttribute::TYPE_SELECT => $names[$raw] ?? null,
                ProductAttribute::TYPE_MULTISELECT, ProductAttribute::TYPE_CHECKBOX => collect((array) $raw)->map(fn ($id) => $names[$id] ?? null)->filter()->values()->all() ?: null,
                ProductAttribute::TYPE_IMAGE, ProductAttribute::TYPE_FILE => Media::url($raw),
                default => $raw,
            };

            if ($value !== null) {
                $values[$attribute->code] = $value;
            }
        }

        return $values;
    }

    /**
     * Özellik/seçenek başına kullanan ürün sayısı (silme koruması ve ayar listesi için).
     * jsonb sorgusu yerine PHP'de sayılır: sqlite testleriyle aynı yol, ürün sayısı küçük.
     *
     * @return array{attributes: array<string, int>, options: array<int, int>}
     */
    public function usage(): array
    {
        $attributes = [];
        $options = [];
        $optionCodes = ProductAttribute::whereIn('type', ProductAttribute::OPTION_TYPES)->pluck('code')->flip();

        Product::withTrashed()->whereNotNull('attributes')->select(['id', 'attributes'])->lazyById()
            ->each(function (Product $product) use (&$attributes, &$options, $optionCodes) {
                foreach ($product->attributes ?? [] as $code => $value) {
                    $attributes[$code] = ($attributes[$code] ?? 0) + 1;
                    if (! $optionCodes->has($code)) {
                        continue;
                    }
                    foreach ((array) $value as $v) {
                        $options[(int) $v] = ($options[(int) $v] ?? 0) + 1;
                    }
                }
            });

        return ['attributes' => $attributes, 'options' => $options];
    }

    /** Ayar değişince (özellik/seçenek/kategori ataması) aynı istek içinde bayat kalmasın. */
    public function flush(): void
    {
        $this->byCategory = null;
    }
}
