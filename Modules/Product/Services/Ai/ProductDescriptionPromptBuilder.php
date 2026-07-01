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
Sen bir moda ürün metin yazarısın. Aşağıdaki ürüne iki farklı tonda Türkçe açıklama üret ve
YALNIZCA aşağıdaki JSON şemasını döndür — başka hiçbir açıklama, kod bloğu, markdown başlığı ekleme:

{
  "public_description": "…",
  "tenant_description": "…"
}

## public_description
Son müşteri (B2C storefront) için hikaye anlatan, duyusal ve arzu uyandıran bir metin. 120-180 kelime.
Markdown kullanabilirsin (**kalın**, *italik*, - liste). Fiyat söyleme, kategori/beden söyleme.

## tenant_description
Bayilerimize (B2B) yönelik spec-forward, satılabilirliği anlatan bir metin. Kompozisyon, gramaj,
dokuma, bakım, dayanıklılık gibi teknik nitelikleri öne çıkar. 100-150 kelime. Markdown kullan.
{$tenantLine}

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
