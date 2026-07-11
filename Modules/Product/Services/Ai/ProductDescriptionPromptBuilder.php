<?php

namespace Modules\Product\Services\Ai;

use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;

/**
 * Gemini JSON-mode prompt üreticisi. İki farklı ton talep eder ve
 * response JSON şablonunu prompt'un içine gömer.
 */
class ProductDescriptionPromptBuilder
{
    public function build(Product $product, Collection $materialLines, ?Tenant $tenant = null): string
    {
        $brand    = $product->brand?->name ?: '—';
        $category = $product->category?->name ?: '—';
        $material = collect($materialLines)->all();
        $materialBlock = $material === []
            ? '- (Materyal tanımlanmamış — genel bir moda dili kullan.)'
            : "- " . implode("\n- ", $material);

        $tenantLine = $tenant
            ? "Bu metin özellikle \"{$tenant->name}\" adlı bayimize özel yazılacak. Onlara doğrudan hitap etmene gerek yok; sadece profesyonel B2B tonu koru."
            : "Bu metin geniş bayi ağımıza sunulacak varsayılan B2B açıklamadır.";

        return <<<PROMPT
Sen bir moda e-ticaret metin yazarı ve SEO uzmanısın. Aşağıdaki ürüne iki farklı tonda Türkçe
açıklama VE SEO'ya uygun bir başlık/meta seti üret. YALNIZCA aşağıdaki JSON şemasını döndür —
başka hiçbir açıklama, kod bloğu, markdown başlığı ekleme:

{
  "public_name": "…",
  "public_description": "…",
  "tenant_description": "…",
  "meta_title": "…",
  "meta_description": "…",
  "meta_keywords": "…"
}

## public_name
Storefront'ta görünecek SEO uyumlu ürün başlığı. Marka + ürün tipi + öne çıkan bir özellik içersin.
Doğal, aranabilir ve çekici olsun. En fazla 60 karakter. Sadece düz metin (markdown yok).

## public_description
Son müşteri (B2C storefront) için hikaye anlatan, duyusal ve arzu uyandıran bir metin. 120-180 kelime.
Markdown kullanabilirsin (**kalın**, *italik*, - liste). Fiyat söyleme, kategori/beden söyleme.

## tenant_description
Bayilerimize (B2B) yönelik spec-forward, satılabilirliği anlatan bir metin. Kompozisyon, gramaj,
dokuma, bakım, dayanıklılık gibi teknik nitelikleri öne çıkar. 100-150 kelime. Markdown kullan.
{$tenantLine}

## meta_title
Arama motoru başlığı (title tag). Marka + ana anahtar kelime. En fazla 60 karakter. Düz metin.

## meta_description
Arama motoru açıklaması (meta description). Tıklamayı teşvik eden, anahtar kelime içeren tek cümle.
En fazla 155 karakter. Düz metin.

## meta_keywords
Virgülle ayrılmış 5-8 alakalı anahtar kelime (marka, ürün tipi, materyal, kullanım). Düz metin.

## Ürün
- Ad: {$product->name}
- Marka: {$brand}
- Kategori: {$category}
- Cinsiyet: {$product->gender}

## Materyaller
{$materialBlock}

Sadece geçerli JSON döndür. Ek metin yazma.
PROMPT;
    }
}
