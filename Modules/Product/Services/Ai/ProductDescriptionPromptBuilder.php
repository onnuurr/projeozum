<?php

namespace Modules\Product\Services\Ai;

use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductAttributeService;
use Modules\Tenant\Models\Tenant;

/**
 * Gemini JSON-mode prompt üreticisi. İstenen bölümleri (açıklamalar: iki ton; SEO: meta başlık /
 * açıklama / anahtar kelime) tek çağrıda ister ve cevap şemasını prompt'a gömer. Ürün bilgisi
 * yalnız formdaki gerçek değerlerden gelir (ad, marka, kategori yolu, kategori özellikleri —
 * yıkama talimatı dahil —, kumaş, materyaller) — model bilinmeyen bilgiyi uydurmasın diye boş alan yazılmaz.
 *
 * PROMPT_VERSION: prompt değişince artırılır (cevapta döner; eski üretimler ayırt edilebilsin).
 */
class ProductDescriptionPromptBuilder
{
    public const PROMPT_VERSION = 'product-content.v4';

    public const PART_DESCRIPTIONS = 'descriptions';

    public const PART_SEO = 'seo';

    public const PARTS = [self::PART_DESCRIPTIONS, self::PART_SEO];

    public function __construct(private readonly ProductAttributeService $attributes) {}

    /** @param  list<string>  $parts */
    public function build(Product $product, Collection $materialLines, ?Tenant $tenant = null, array $parts = [self::PART_DESCRIPTIONS]): string
    {
        $wantsDescriptions = in_array(self::PART_DESCRIPTIONS, $parts, true);
        $wantsSeo = in_array(self::PART_SEO, $parts, true);

        $schema = array_filter([
            $wantsDescriptions ? "  \"public_name\": \"…\",\n  \"public_description\": \"…\",\n  \"tenant_description\": \"…\"" : null,
            $wantsSeo ? "  \"seo\": {\n    \"meta_title\": \"…\",\n    \"meta_description\": \"…\",\n    \"meta_keywords\": [\"…\"]\n  }" : null,
        ]);
        $schemaBlock = "{\n".implode(",\n", $schema)."\n}";

        $sections = [];

        if ($wantsDescriptions) {
            $tenantLine = $tenant
                ? "Bu metin özellikle \"{$tenant->name}\" adlı bayimize özel yazılacak. Onlara doğrudan hitap etmene gerek yok; sadece profesyonel B2B tonu koru."
                : 'Bu metin geniş bayi ağımıza sunulacak varsayılan B2B açıklamadır.';

            $sections[] = <<<TXT
## public_name
Mağazada (B2C storefront) görünecek ürün adı. 3-8 kelime, en fazla 70 karakter. Ürün tipini ve en ayırt
edici 1-2 özelliği (kumaş, kalıp, yaka vb.) içersin; marka adı, fiyat, emoji, büyük harfle yazılmış kelime
ve tırnak kullanma. Örnek biçim: "Viskon Krep V Yaka Midi Elbise".

## public_description
Son müşteri (B2C storefront) için hikaye anlatan, duyusal ve arzu uyandıran bir metin. 120-180 kelime.
Markdown kullanabilirsin (**kalın**, *italik*, - liste). Fiyat söyleme, beden söyleme.

## tenant_description
Bayilerimize (B2B) yönelik spec-forward, satılabilirliği anlatan bir metin. Kompozisyon, gramaj,
dokuma, bakım, dayanıklılık gibi teknik nitelikleri öne çıkar. 100-150 kelime. Markdown kullan.
{$tenantLine}
TXT;
        }

        if ($wantsSeo) {
            $sections[] = <<<'TXT'
## seo
Arama motoru (Google) için Türkçe metinler; markdown ve emoji kullanma.
- meta_title: en fazla 60 karakter. Ürün adı başta; marka ve ayırt edici özellik (kumaş, kalıp vb.) sığarsa ekle.
- meta_description: 140-160 karakter. Ürünün öne çıkan özelliklerini anlatan, tıklamaya davet eden tek paragraf. Fiyat, indirim, kargo vaadi yazma.
- meta_keywords: 5-10 arası kısa arama ifadesi (küçük harf), dizi olarak. Marka, kategori ve ürün tipi ile özelliklerden türet; alakasız ya da tekrar eden ifade yazma.
TXT;
        }

        $productBlock = '- '.implode("\n- ", $this->productLines($product));
        $materials = collect($materialLines)->all();
        $materialBlock = $materials === []
            ? '- (Materyal tanımlanmamış — kumaş hakkında bilgi uydurma, genel bir moda dili kullan.)'
            : '- '.implode("\n- ", $materials);
        $sectionBlock = implode("\n\n", $sections);

        return <<<PROMPT
Sen bir moda e-ticaret metin yazarısın. Aşağıdaki ürün için Türkçe metinler üret ve
YALNIZCA aşağıdaki JSON şemasını döndür — başka hiçbir açıklama, kod bloğu, markdown başlığı ekleme:

{$schemaBlock}

{$sectionBlock}

## Ürün
{$productBlock}

## Materyaller
{$materialBlock}

Yalnızca yukarıdaki bilgileri kullan; verilmeyen bir özelliği (kumaş, menşei, sertifika vb.) uydurma.
Sadece geçerli JSON döndür. Ek metin yazma.
PROMPT;
    }

    /** @return list<string> */
    private function productLines(Product $product): array
    {
        $category = $product->category;
        $categoryPath = $category ? implode(' > ', array_filter([$category->parent?->name, $category->name])) : null;

        $lines = array_filter([
            'Ad' => $product->name,
            'Marka' => $product->brand?->name,
            'Kategori' => $categoryPath,
            'Kumaş içeriği' => $product->material,
        ], fn ($v) => filled($v));

        // Kategori özellikleri (kalıp, yaka tipi, cinsiyet …): okunur ad + değer. KDV ve dosya değerleri hariç.
        $names = collect($this->attributes->definitionsFor($product->category_id))
            ->reject(fn (array $d) => $d['attribute']->isSystem() || $d['attribute']->isUpload())
            ->mapWithKeys(fn (array $d) => [$d['attribute']->code => $d['attribute']->name]);
        foreach ($this->attributes->labelledValues($product) as $code => $value) {
            if (! $names->has($code)) {
                continue;
            }
            $text = is_bool($value) ? ($value ? 'Evet' : 'Hayır') : (is_array($value) ? implode(', ', $value) : (string) $value);
            if ($text !== '') {
                $lines[$names[$code]] = $text;
            }
        }

        return collect($lines)->map(fn ($v, $k) => "{$k}: {$v}")->values()->all();
    }
}
